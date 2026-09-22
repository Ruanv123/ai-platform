<?php

namespace App\Http\Controllers;

use App\Consent\ContactConsentService;
use App\Enums\FlowExecutionStatus;
use App\Enums\MessageDirection;
use App\Http\Requests\SendHumanMessageRequest;
use App\Inbox\HumanInboxService;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\FlowExecution;
use App\Models\Message;
use App\Models\User;
use App\WhatsApp\WhatsAppCustomerCareWindow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

final class InboxController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->render($request);
    }

    public function show(Request $request, Contact $contact): Response
    {
        abort_unless($this->waitingQuery()->where('contact_id', $contact->getKey())->exists(), 404);

        return $this->render($request, $contact);
    }

    public function take(Request $request, Contact $contact, HumanInboxService $inbox): RedirectResponse
    {
        $inbox->assign($contact, $request->user());

        return to_route('inbox.show', $contact);
    }

    public function storeMessage(
        SendHumanMessageRequest $request,
        Contact $contact,
        HumanInboxService $inbox,
    ): RedirectResponse {
        $inbox->send($contact, $request->user(), $request->validated());

        return to_route('inbox.show', $contact);
    }

    public function returnToBot(
        Request $request,
        Contact $contact,
        HumanInboxService $inbox,
    ): RedirectResponse {
        $inbox->returnToBot($contact);

        return to_route('inbox.index');
    }

    private function render(Request $request, ?Contact $selectedContact = null): Response
    {
        $team = $request->user()->company();
        abort_if($team === null, 404);

        $baseQuery = $this->waitingQuery();
        $this->applyFilters($baseQuery, $request);
        $counts = [
            'awaiting' => (clone $baseQuery)->whereNull('human_assigned_user_id')->count(),
            'mine' => (clone $baseQuery)->where('human_assigned_user_id', $request->user()->getKey())->count(),
            'all' => (clone $baseQuery)->count(),
        ];

        $listQuery = clone $baseQuery;
        $this->applyQueue($listQuery, $request);
        $this->applyAssignmentFilter($listQuery, $request);
        $conversations = $listQuery
            ->with([
                'contact.latestMessage',
                'channel',
                'flowVersion.flow',
                'humanAssignee',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $selectedExecution = $selectedContact === null
            ? null
            : $this->waitingQuery()
                ->where('contact_id', $selectedContact->getKey())
                ->with([
                    'contact.fieldValues.field',
                    'contact.tags',
                    'channel',
                    'flowVersion.flow',
                    'humanAssignee',
                ])
                ->latest('created_at')
                ->first();

        return Inertia::render('inbox/index', [
            'conversations' => [
                'data' => $conversations->getCollection()->map(fn (FlowExecution $execution): array => $this->conversation($execution))->values(),
                'currentPage' => $conversations->currentPage(),
                'lastPage' => $conversations->lastPage(),
                'total' => $conversations->total(),
            ],
            'counts' => $counts,
            'filters' => [
                'queue' => $request->string('queue', 'awaiting')->toString(),
                'assignedTo' => $request->input('assigned_to'),
                'channel' => $request->input('channel'),
                'search' => $request->string('search')->toString(),
            ],
            'members' => $team->members()
                ->select('users.id', 'users.name')
                ->orderBy('users.name')
                ->get()
                ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name])
                ->values(),
            'channels' => $team->channels()
                ->orderBy('type')
                ->get(['id', 'type'])
                ->map(fn (Channel $channel): array => [
                    'id' => $channel->id,
                    'label' => $channel->type->label(),
                ])
                ->values(),
            'selected' => $selectedExecution === null ? null : $this->selectedConversation($selectedExecution, $request),
            'currentUserId' => $request->user()->getKey(),
        ]);
    }

    /** @return Builder<FlowExecution> */
    private function waitingQuery(): Builder
    {
        return FlowExecution::query()
            ->where('status', FlowExecutionStatus::Paused)
            ->where('waiting_for', 'human')
            ->whereNotNull('contact_id');
    }

    /** @param Builder<FlowExecution> $query */
    private function applyFilters(Builder $query, Request $request): void
    {
        $search = trim($request->string('search')->toString());
        $channel = $request->string('channel')->toString();

        if ($search !== '') {
            $query->whereHas('contact', function (Builder $contactQuery) use ($search): void {
                $contactQuery->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%');
                });
            });
        }

        if ($channel !== '') {
            $query->where('channel_id', $channel);
        }
    }

    /** @param Builder<FlowExecution> $query */
    private function applyQueue(Builder $query, Request $request): void
    {
        match ($request->string('queue', 'awaiting')->toString()) {
            'mine' => $query->where('human_assigned_user_id', $request->user()->getKey()),
            'all' => null,
            default => $query->whereNull('human_assigned_user_id'),
        };
    }

    /** @param Builder<FlowExecution> $query */
    private function applyAssignmentFilter(Builder $query, Request $request): void
    {
        $assignedTo = $request->input('assigned_to');
        if ($assignedTo === 'unassigned') {
            $query->whereNull('human_assigned_user_id');
        } elseif (is_numeric($assignedTo)) {
            $query->where('human_assigned_user_id', (int) $assignedTo);
        }
    }

    /** @return array<string, mixed> */
    private function conversation(FlowExecution $execution): array
    {
        $contact = $execution->contact;
        $latestMessage = $contact?->latestMessage;
        $excerpt = $latestMessage?->content;
        if (blank($excerpt)) {
            $excerpt = 'Sem Mensagens de texto.';
        }

        return [
            'id' => $contact?->getKey(),
            'name' => $contact?->name,
            'phone' => $contact?->phone,
            'excerpt' => $excerpt,
            'lastMessageAt' => $latestMessage?->created_at?->toIso8601String(),
            'waitingSince' => $contact?->human_waiting_at?->toIso8601String(),
            'waitingSeconds' => $contact?->human_waiting_at === null ? 0 : max(0, (int) $contact->human_waiting_at->diffInSeconds(now())),
            'reason' => $this->reason($execution->human_transfer_reason),
            'channel' => $execution->channel === null ? null : [
                'id' => $execution->channel->id,
                'label' => $execution->channel->type->label(),
            ],
            'assignee' => $execution->humanAssignee === null ? null : [
                'id' => $execution->humanAssignee->id,
                'name' => $execution->humanAssignee->name,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function selectedConversation(FlowExecution $execution, Request $request): array
    {
        $contact = $execution->contact;
        $channel = $execution->channel;
        abort_if($contact === null || $channel === null, 404);

        $messages = Message::query()
            ->where('contact_id', $contact->getKey())
            ->where('channel_id', $channel->getKey())
            ->with(['sentBy', 'channel'])
            ->latest('created_at')
            ->latest('id')
            ->paginate(100, ['*'], 'messages_page')
            ->withQueryString();
        $messages->setCollection($messages->getCollection()->reverse()->values());

        $history = Message::query()
            ->where('contact_id', $contact->getKey())
            ->with(['sentBy', 'channel'])
            ->latest('created_at')
            ->latest('id')
            ->paginate(50, ['*'], 'history_page')
            ->withQueryString();
        $window = app(WhatsAppCustomerCareWindow::class);
        $lastIncoming = Message::query()
            ->where('contact_id', $contact->getKey())
            ->where('channel_id', $channel->getKey())
            ->where('direction', MessageDirection::Incoming)
            ->latest('created_at')
            ->latest('id')
            ->first();
        $expiresAt = $window->expiresAt($channel, $lastIncoming);
        $flow = $execution->flowVersion->flow;
        $consents = app(ContactConsentService::class)->snapshot($contact);

        return [
            'contact' => [
                'id' => $contact->id,
                'name' => $contact->name,
                'phone' => $contact->phone,
                'fields' => $contact->fieldValues
                    ->filter(fn ($value): bool => $value->field !== null)
                    ->map(fn ($value): array => [
                        'name' => $value->field->name,
                        'value' => $value->field->is_sensitive ? '••••••••' : $value->value,
                        'sensitive' => $value->field->is_sensitive,
                    ])
                    ->values(),
                'tags' => $contact->tags->map(fn ($tag): array => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                ])->values(),
                'consents' => $consents,
            ],
            'channel' => [
                'id' => $channel->id,
                'label' => $channel->type->label(),
            ],
            'assignee' => $execution->humanAssignee === null ? null : [
                'id' => $execution->humanAssignee->id,
                'name' => $execution->humanAssignee->name,
            ],
            'reason' => $this->reason($execution->human_transfer_reason),
            'execution' => [
                'id' => $execution->id,
                'flowId' => $flow->id,
                'flowName' => $flow->name,
                'version' => $execution->flowVersion->number,
                'nodeId' => $execution->current_node_id,
                'nodeName' => $this->nodeName($execution),
                'traceUrl' => route('flows.executions.show', [$flow, $execution]),
                'status' => $execution->status->value,
            ],
            'messages' => $this->messagePaginator($messages),
            'history' => $this->messagePaginator($history),
            'window' => [
                'open' => ! $window->hasExpired($channel, $lastIncoming),
                'expiresAt' => $expiresAt?->toIso8601String(),
                'remainingSeconds' => $window->remainingSeconds($channel, $lastIncoming),
            ],
            'canWrite' => $execution->human_assigned_user_id === $request->user()->getKey(),
        ];
    }

    /**
     * @param  LengthAwarePaginator<int, Message>  $messages
     * @return array<string, mixed>
     */
    private function messagePaginator(LengthAwarePaginator $messages): array
    {
        return [
            'data' => $messages->getCollection()->map(function (Message $message): array {
                $sender = $message->direction === MessageDirection::Incoming
                    ? 'Contato'
                    : 'Agente';
                if ($message->sentBy !== null) {
                    $sender = $message->sentBy->name;
                }

                return [
                    'id' => $message->id,
                    'direction' => $message->direction->value,
                    'sender' => $sender,
                    'content' => $message->content,
                    'payloadType' => $message->payload_type,
                    'channel' => $message->channel->type->label(),
                    'createdAt' => $message->created_at?->toIso8601String(),
                    'deliveryStatus' => $message->delivery_status?->value,
                ];
            })->values(),
            'currentPage' => $messages->currentPage(),
            'lastPage' => $messages->lastPage(),
            'nextUrl' => $messages->nextPageUrl(),
            'previousUrl' => $messages->previousPageUrl(),
        ];
    }

    private function nodeName(FlowExecution $execution): string
    {
        $nodes = $execution->flowVersion->dsl['nodes'] ?? [];
        $node = collect(is_array($nodes) ? $nodes : [])->firstWhere('id', $execution->current_node_id);

        return is_array($node)
            ? (string) data_get($node, 'config.label', data_get($node, 'label', $execution->current_node_id ?? 'Nó desconhecido'))
            : ($execution->current_node_id ?? 'Nó desconhecido');
    }

    /** @return array{code: string, label: string} */
    private function reason(?string $reason): array
    {
        $code = $reason ?: 'contact_requested';
        $labels = [
            'contact_requested' => 'Pedido do Contato',
            'exception_exhausted' => 'Escada de exceção esgotada',
            'unsupported_media' => 'Mídia não suportada',
        ];

        return ['code' => $code, 'label' => $labels[$code] ?? $code];
    }
}
