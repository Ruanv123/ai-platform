<?php

namespace App\Http\Controllers\Agents;

use App\Ai\Agents\PlaygroundAgent;
use App\Ecommerce\ProductCatalogSearch;
use App\Http\Controllers\Controller;
use App\Http\Requests\Knowledge\PlaygroundRequest;
use App\Knowledge\AgentInstructionTemplate;
use App\Knowledge\KnowledgeBaseSearch;
use App\Models\Agent;
use App\Models\Collection;
use App\Models\Contact;
use App\Models\Team;
use App\Support\E164PhoneNumber;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laravel\Ai\Streaming\Events\Error;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class PlaygroundController extends Controller
{
    /**
     * Stream one draft-Agent response without persisting the conversation.
     */
    public function stream(
        PlaygroundRequest $request,
        AgentInstructionTemplate $instructionTemplate,
        KnowledgeBaseSearch $knowledgeBaseSearch,
        ProductCatalogSearch $productCatalogSearch,
    ): StreamedResponse {
        $validated = $request->validated();
        $team = $request->user()->company();
        abort_if($team === null, 404);

        $draftAgent = (new Agent)->fill(Arr::except($validated, [
            'history',
            'message',
            'collection_ids',
            'schedulable_calendar_ids',
            'crm_lookup_phone',
        ]));
        $draftAgent->setRelation('team', $team);
        $draftAgent->setRelation(
            'collections',
            $this->draftCollections($team, $validated['collection_ids'] ?? []),
        );
        $draftAgent->setRelation(
            'schedulableCalendars',
            $team->schedulableCalendars()->whereKey($validated['schedulable_calendar_ids'] ?? [])->with('integration')->get(),
        );

        $crmLookupPhone = $validated['crm_lookup_phone'] ?? null;
        $contact = $this->playgroundContact($team, $crmLookupPhone);

        return response()->stream(function () use (
            $draftAgent,
            $validated,
            $instructionTemplate,
            $knowledgeBaseSearch,
            $productCatalogSearch,
            $crmLookupPhone,
            $contact,
        ): void {
            $invocationId = (string) Str::uuid7();

            try {
                $playgroundAgent = new PlaygroundAgent(
                    $draftAgent,
                    $validated['history'] ?? [],
                    $instructionTemplate,
                    $knowledgeBaseSearch,
                    $productCatalogSearch,
                    crmLookupPhone: $crmLookupPhone,
                    contact: $contact,
                );
                $stream = $playgroundAgent->stream(
                    $validated['message'],
                    provider: config('ai.playground.provider'),
                    model: config('ai.playground.model'),
                    timeout: (int) config('ai.playground.timeout', 60),
                );
                $invocationId = $stream->invocationId;

                foreach ($stream as $event) {
                    $this->writeEvent((string) $event);
                }

                $this->writeEvent('[DONE]');
            } catch (Throwable $exception) {
                report($exception);

                $this->writeEvent((string) (new Error(
                    $invocationId,
                    'error',
                    'Não foi possível obter uma resposta agora. Tente novamente em instantes.',
                    true,
                    time(),
                ))->withInvocationId($invocationId));
                $this->writeEvent('[DONE]');
            }
        }, headers: [
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Content-Type' => 'text/event-stream',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Load only the validated Collections belonging to the current Empresa.
     *
     * @param  list<string>  $collectionIds
     * @return \Illuminate\Database\Eloquent\Collection<int, Collection>
     */
    private function draftCollections(Team $team, array $collectionIds): \Illuminate\Database\Eloquent\Collection
    {
        return $team->collections()->whereKey($collectionIds)->get();
    }

    private function playgroundContact(Team $team, ?string $phone): ?Contact
    {
        if ($phone === null) {
            return null;
        }

        $normalizedPhone = E164PhoneNumber::formatForCountry($phone, 'BR');

        if ($normalizedPhone === null) {
            return null;
        }

        return Contact::query()
            ->where('team_id', $team->getKey())
            ->where('phone', $normalizedPhone)
            ->first();
    }

    /**
     * Write one event using the Laravel AI Server-Sent Events protocol.
     */
    private function writeEvent(string $event): void
    {
        echo "data: {$event}\n\n";

        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();
    }
}
