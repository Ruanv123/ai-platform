<?php

namespace App\Http\Controllers;

use App\Consent\ContactConsentService;
use App\Enums\ChannelStatus;
use App\Enums\ChannelType;
use App\Enums\MessageDirection;
use App\Enums\WhatsAppTemplateFormat;
use App\Enums\WhatsAppTemplateStatus;
use App\Http\Requests\SendHumanMessageRequest;
use App\Inbox\AgentConversationService;
use App\Models\AgentConversationState;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\ConversationRead;
use App\Models\Message;
use App\Models\User;
use App\Models\WhatsAppTemplate;
use App\WhatsApp\WhatsAppCustomerCareWindow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Inertia\Inertia;
use Inertia\Response;

final class InboxController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->render($request);
    }

    public function show(Request $request, Contact $contact, Channel $channel): Response
    {
        abort_unless($this->conversationMessages($request->user())
            ->where('contact_id', $contact->getKey())
            ->where('channel_id', $channel->getKey())
            ->exists(), 404);

        $this->markConversationAsRead($contact, $channel);

        return $this->render($request, $contact, $channel);
    }

    public function pauseAgent(
        Request $request,
        Contact $contact,
        Channel $channel,
        AgentConversationService $conversations,
    ): RedirectResponse {
        $conversations->pauseAndAssign($contact, $channel, $request->user());

        return to_route('inbox.show', [$contact, $channel]);
    }

    public function takeOverAgent(
        Request $request,
        Contact $contact,
        Channel $channel,
        AgentConversationService $conversations,
    ): RedirectResponse {
        $conversations->takeOver($contact, $channel, $request->user());

        return to_route('inbox.show', [$contact, $channel]);
    }

    public function storeMessage(
        SendHumanMessageRequest $request,
        Contact $contact,
        Channel $channel,
        AgentConversationService $conversations,
    ): RedirectResponse {
        $conversations->send($contact, $channel, $request->user(), $request->validated());

        return to_route('inbox.show', [$contact, $channel]);
    }

    public function returnToAgent(
        Request $request,
        Contact $contact,
        Channel $channel,
        AgentConversationService $conversations,
    ): RedirectResponse {
        $conversations->returnToAgent($contact, $channel, $request->user());

        return to_route('inbox.show', [$contact, $channel]);
    }

    private function render(Request $request, ?Contact $selectedContact = null, ?Channel $selectedChannel = null): Response
    {
        $team = $request->user()->company();
        abort_if($team === null, 404);

        $baseQuery = $this->conversationMessages($request->user());
        $this->applyFilters($baseQuery, $request);
        $counts = [
            'awaiting' => $this->countWaitingConversations(clone $baseQuery, unassigned: true),
            'mine' => $this->countWaitingConversations(clone $baseQuery, userId: $request->user()->getKey()),
            'all' => (clone $baseQuery)->count(),
        ];

        $listQuery = clone $baseQuery;
        $this->applyQueue($listQuery, $request);
        $this->applyAssignmentFilter($listQuery, $request);
        $conversations = $listQuery
            ->with([
                'contact',
                'channel',
            ])
            ->orderByDesc('messages.created_at')
            ->orderByDesc('messages.id')
            ->paginate(20)
            ->withQueryString();

        $agentStatesByConversation = $this->agentStatesFor($conversations);
        $unreadCountsByConversation = $this->unreadCountsFor($conversations);

        $selectedAgentState = $selectedContact === null || $selectedChannel === null
            ? null
            : app(AgentConversationService::class)->stateFor($selectedContact, $selectedChannel);

        return Inertia::render('inbox/index', [
            'conversations' => [
                'data' => $conversations->getCollection()->map(fn (Message $message): array => $this->conversation(
                    $message,
                    $agentStatesByConversation->get($this->conversationKey($message->contact_id, $message->channel_id)),
                    $unreadCountsByConversation->get($this->conversationKey($message->contact_id, $message->channel_id), 0),
                ))->values(),
                'currentPage' => $conversations->currentPage(),
                'lastPage' => $conversations->lastPage(),
                'total' => $conversations->total(),
            ],
            'counts' => $counts,
            'filters' => [
                'queue' => $request->string('queue', 'all')->toString(),
                'assignedTo' => $request->input('assigned_to'),
                'channel' => $request->input('channel'),
                'state' => $request->string('state', 'all')->toString(),
                'search' => $request->string('search')->toString(),
            ],
            'members' => $team->members()
                ->select('users.id', 'users.name')
                ->orderBy('users.name')
                ->get()
                ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name])
                ->values(),
            'channels' => $team->channels()
                ->whereIn('type', $this->visibleWhatsAppTypes($request->user()))
                ->orderBy('type')
                ->get(['id', 'type', 'status'])
                ->map(fn (Channel $channel): array => [
                    'id' => $channel->id,
                    'label' => $channel->type->label(),
                    'status' => $channel->status->value,
                    'statusLabel' => $channel->status->label(),
                ])
                ->values(),
            'selected' => $selectedContact === null || $selectedChannel === null
                ? null
                : $this->selectedConversation($selectedContact, $selectedChannel, $selectedAgentState, $request),
            'currentUserId' => $request->user()->getKey(),
        ]);
    }

    /** @return Builder<Message> */
    private function conversationMessages(User $user): Builder
    {
        $query = Message::query()
            ->whereHas('channel', fn (Builder $channelQuery): Builder => $channelQuery
                ->whereIn('type', $this->visibleWhatsAppTypes($user)))
            ->whereNotExists(function (QueryBuilder $query): void {
                $query->selectRaw('1')
                    ->from('messages as newer_messages')
                    ->whereColumn('newer_messages.team_id', 'messages.team_id')
                    ->whereColumn('newer_messages.contact_id', 'messages.contact_id')
                    ->whereColumn('newer_messages.channel_id', 'messages.channel_id')
                    ->where(function (QueryBuilder $query): void {
                        $query->whereColumn('newer_messages.created_at', '>', 'messages.created_at')
                            ->orWhere(function (QueryBuilder $query): void {
                                $query->whereColumn('newer_messages.created_at', 'messages.created_at')
                                    ->whereColumn('newer_messages.id', '>', 'messages.id');
                            });
                    });
            });

        return $query;
    }

    /** @return list<string> */
    private function visibleWhatsAppTypes(User $user): array
    {
        return array_values(array_map(
            fn (ChannelType $type): string => $type->value,
            array_filter(
                [ChannelType::WhatsApp, ChannelType::WhatsAppTest],
                fn (ChannelType $type): bool => $type->isVisible($user),
            ),
        ));
    }

    /** @param Builder<Message> $query */
    private function countWaitingConversations(Builder $query, bool $unassigned = false, ?int $userId = null): int
    {
        $query->whereExists(function (QueryBuilder $subquery) use ($unassigned, $userId): void {
            $this->agentPauseSubquery($subquery);

            if ($unassigned) {
                $subquery->whereNull('agent_conversation_states.assigned_user_id');
            } elseif ($userId !== null) {
                $subquery->where('agent_conversation_states.assigned_user_id', $userId);
            }
        });

        return $query->count();
    }

    /** @return Collection<int, AgentConversationState> */
    private function agentStatesFor(LengthAwarePaginator $conversations): Collection
    {
        $messages = $conversations->getCollection();
        if ($messages->isEmpty()) {
            return new Collection;
        }

        return AgentConversationState::withoutGlobalScopes()
            ->where('team_id', $messages->first()->team_id)
            ->where(function (Builder $query) use ($messages): void {
                foreach ($messages as $message) {
                    $query->orWhere(function (Builder $pairQuery) use ($message): void {
                        $pairQuery->where('contact_id', $message->contact_id)
                            ->where('channel_id', $message->channel_id);
                    });
                }
            })
            ->with('assignedUser')
            ->get()
            ->keyBy(fn (AgentConversationState $state): string => $this->conversationKey(
                (int) $state->contact_id,
                (string) $state->channel_id,
            ));
    }

    private function markConversationAsRead(Contact $contact, Channel $channel): void
    {
        $latestMessageId = Message::query()
            ->where('contact_id', $contact->getKey())
            ->where('channel_id', $channel->getKey())
            ->max('id');

        if ($latestMessageId === null) {
            return;
        }

        $read = ConversationRead::query()->firstOrNew([
            'team_id' => $contact->team_id,
            'contact_id' => $contact->getKey(),
            'channel_id' => $channel->getKey(),
        ]);

        if ($read->exists && $read->last_read_message_id >= $latestMessageId) {
            return;
        }

        $read->last_read_message_id = $latestMessageId;
        $read->save();
    }

    /** @return SupportCollection<string, int> */
    private function unreadCountsFor(LengthAwarePaginator $conversations): SupportCollection
    {
        $messages = $conversations->getCollection();
        if ($messages->isEmpty()) {
            return collect();
        }

        return Message::query()
            ->leftJoin('conversation_reads', function (JoinClause $join): void {
                $join->on('conversation_reads.team_id', '=', 'messages.team_id')
                    ->on('conversation_reads.contact_id', '=', 'messages.contact_id')
                    ->on('conversation_reads.channel_id', '=', 'messages.channel_id');
            })
            ->where('messages.direction', MessageDirection::Incoming)
            ->whereRaw('messages.id > coalesce(conversation_reads.last_read_message_id, 0)')
            ->where(function (Builder $query) use ($messages): void {
                foreach ($messages as $message) {
                    $query->orWhere(function (Builder $pairQuery) use ($message): void {
                        $pairQuery->where('messages.contact_id', $message->contact_id)
                            ->where('messages.channel_id', $message->channel_id);
                    });
                }
            })
            ->groupBy('messages.contact_id', 'messages.channel_id')
            ->selectRaw('messages.contact_id, messages.channel_id, count(*) as unread_count')
            ->get()
            ->mapWithKeys(fn (Message $row): array => [
                $this->conversationKey((int) $row->contact_id, (string) $row->channel_id) => (int) $row->getAttribute('unread_count'),
            ]);
    }

    private function conversationKey(int $contactId, string $channelId): string
    {
        return $contactId.':'.$channelId;
    }

    private function agentPauseSubquery(QueryBuilder $query): void
    {
        $query->selectRaw('1')
            ->from('agent_conversation_states')
            ->whereColumn('agent_conversation_states.team_id', 'messages.team_id')
            ->whereColumn('agent_conversation_states.contact_id', 'messages.contact_id')
            ->whereColumn('agent_conversation_states.channel_id', 'messages.channel_id')
            ->where('agent_conversation_states.is_paused', true);
    }

    /** @param Builder<Message> $query */
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
            $query->where('messages.channel_id', $channel);
        }

        match ($request->string('state', 'all')->toString()) {
            'agent' => $query->whereNotExists(function (QueryBuilder $subquery): void {
                $this->agentPauseSubquery($subquery);
            }),
            'paused' => $query->whereExists(function (QueryBuilder $subquery): void {
                $this->agentPauseSubquery($subquery);
            }),
            default => null,
        };
    }

    /** @param Builder<Message> $query */
    private function applyQueue(Builder $query, Request $request): void
    {
        match ($request->string('queue', 'all')->toString()) {
            'mine' => $query->whereExists(function (QueryBuilder $subquery) use ($request): void {
                $this->agentPauseSubquery($subquery);
                $subquery->where('agent_conversation_states.assigned_user_id', $request->user()->getKey());
            }),
            'all' => null,
            default => $query->whereExists(function (QueryBuilder $subquery): void {
                $this->agentPauseSubquery($subquery);
                $subquery->whereNull('agent_conversation_states.assigned_user_id');
            }),
        };
    }

    /** @param Builder<Message> $query */
    private function applyAssignmentFilter(Builder $query, Request $request): void
    {
        $assignedTo = $request->input('assigned_to');
        if ($assignedTo === 'unassigned') {
            $query->whereExists(function (QueryBuilder $subquery): void {
                $this->agentPauseSubquery($subquery);
                $subquery->whereNull('agent_conversation_states.assigned_user_id');
            });
        } elseif (is_numeric($assignedTo)) {
            $query->whereExists(function (QueryBuilder $subquery) use ($assignedTo): void {
                $this->agentPauseSubquery($subquery);
                $subquery->where('agent_conversation_states.assigned_user_id', (int) $assignedTo);
            });
        }
    }

    /** @return array<string, mixed> */
    private function conversation(Message $latestMessage, ?AgentConversationState $agentState, int $unreadCount): array
    {
        $contact = $latestMessage->contact;
        $channel = $latestMessage->channel;

        $excerpt = $latestMessage->content;
        if (blank($excerpt)) {
            $excerpt = 'Sem Mensagens de texto.';
        }

        return [
            'contactId' => $contact->getKey(),
            'name' => $contact->name,
            'phone' => $contact->phone,
            'excerpt' => $excerpt,
            'lastMessageAt' => $latestMessage->created_at?->toIso8601String(),
            'unreadCount' => $unreadCount,
            'waitingSeconds' => $latestMessage->created_at === null ? 0 : max(0, (int) $latestMessage->created_at->diffInSeconds(now())),
            'agentPaused' => $agentState?->is_paused === true,
            'channel' => [
                'id' => $channel->id,
                'label' => $channel->type->label(),
                'status' => $channel->status->value,
                'statusLabel' => $channel->status->label(),
                'available' => $channel->status === ChannelStatus::Connected,
            ],
            'assignee' => $agentState?->assignedUser !== null ? [
                'id' => $agentState->assignedUser->id,
                'name' => $agentState->assignedUser->name,
            ] : null,
        ];
    }

    private function templatesForChannel(Channel $channel): array
    {
        if ($channel->type !== ChannelType::WhatsApp) {
            return [];
        }

        $channel->loadMissing('whatsappPhoneNumber.whatsappAccount');
        $account = $channel->whatsappPhoneNumber?->whatsappAccount;
        if ($account === null) {
            return [];
        }

        return $account->templates()
            ->where('status', WhatsAppTemplateStatus::Approved)
            ->where('format', WhatsAppTemplateFormat::Standard)
            ->with('components')
            ->orderBy('name')
            ->get()
            ->map(fn (WhatsAppTemplate $template): array => [
                'name' => $template->name,
                'language' => $template->language,
                'headerType' => $template->headerType(),
                'headerText' => $template->headerText(),
            ])
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function selectedConversation(
        Contact $contact,
        Channel $channel,
        ?AgentConversationState $agentState,
        Request $request,
    ): array {
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
            ->where('channel_id', $channel->getKey())
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
        $consents = app(ContactConsentService::class)->snapshot($contact);

        return [
            'contact' => [
                'id' => $contact->id,
                'name' => $contact->name,
                'phone' => $contact->phone,
                'consents' => $consents,
            ],
            'channel' => [
                'id' => $channel->id,
                'label' => $channel->type->label(),
                'status' => $channel->status->value,
                'statusLabel' => $channel->status->label(),
                'available' => $channel->status === ChannelStatus::Connected,
            ],
            'templates' => $this->templatesForChannel($channel),
            'agentPaused' => $agentState?->is_paused === true,
            'agentPauseExpiresAt' => $agentState?->expires_at?->toIso8601String(),
            'agentPauseDurationMinutes' => $agentState?->pause_duration_minutes,
            'assignee' => $agentState?->assignedUser !== null ? [
                'id' => $agentState->assignedUser->id,
                'name' => $agentState->assignedUser->name,
            ] : null,
            'messages' => $this->messagePaginator($messages),
            'history' => $this->messagePaginator($history),
            'window' => [
                'open' => ! $window->hasExpired($channel, $lastIncoming),
                'expiresAt' => $expiresAt?->toIso8601String(),
                'remainingSeconds' => $window->remainingSeconds($channel, $lastIncoming),
            ],
            'canPause' => ! ($agentState?->is_paused ?? false)
                && app(AgentConversationService::class)->canPause($contact, $channel),
            'canTakeOver' => ($agentState?->is_paused ?? false)
                && $agentState?->assigned_user_id !== $request->user()->getKey(),
            'canReturn' => ($agentState?->is_paused ?? false)
                && $agentState?->assigned_user_id === $request->user()->getKey(),
            'canWrite' => app(AgentConversationService::class)->canPause($contact, $channel)
                && (! ($agentState?->is_paused ?? false)
                    || $agentState?->assigned_user_id === $request->user()->getKey()),
            'canSend' => $channel->status === ChannelStatus::Connected
                && app(AgentConversationService::class)->canPause($contact, $channel)
                && (! ($agentState?->is_paused ?? false)
                    || $agentState?->assigned_user_id === $request->user()->getKey()),
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
                    'failureReason' => $message->failure_reason,
                    'reactionEmoji' => $message->reaction_emoji,
                    'reactedAt' => $message->reacted_at === null
                        ? null
                        : Carbon::parse($message->reacted_at)->toIso8601String(),
                    'mediaAvailable' => $message->media_available ?? false,
                    'mediaUrl' => $message->media_available === true && filled($message->media_path)
                        ? route('inbox.messages.media', [$message->contact_id, $message->channel_id, $message->getKey()])
                        : null,
                    'mediaFilename' => $message->media_filename,
                    'mediaMimeType' => $message->media_mime_type,
                ];
            })->values(),
            'currentPage' => $messages->currentPage(),
            'lastPage' => $messages->lastPage(),
            'nextUrl' => $messages->nextPageUrl(),
            'previousUrl' => $messages->previousPageUrl(),
        ];
    }
}
