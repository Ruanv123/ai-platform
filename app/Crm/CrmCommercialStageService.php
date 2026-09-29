<?php

namespace App\Crm;

use App\Events\CrmRealtimeNotification;
use App\Events\HumanConversationUpdated;
use App\Jobs\RespondToWhatsAppMessage;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\CrmContactLink;
use App\Models\CrmLinkedDeal;
use App\Models\FlowExecution;
use App\Models\Integration;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CrmCommercialStageService
{
    /** @param list<string> $stageIds */
    public function updateCommercialStages(Integration $integration, array $stageIds): void
    {
        $integration->forceFill([
            'crm_settings' => [
                ...($integration->crm_settings ?? []),
                'commercial_stage_ids' => array_values(array_unique($stageIds)),
            ],
        ])->save();

        CrmContactLink::withoutGlobalScopes()
            ->where('team_id', $integration->team_id)
            ->where('integration_id', $integration->getKey())
            ->select('contact_id')
            ->distinct()
            ->orderBy('contact_id')
            ->chunk(100, function (Collection $links) use ($integration): void {
                foreach ($links as $link) {
                    $this->reconcileContact($integration->team_id, (int) $link->contact_id);
                }
            });
    }

    public function reconcileContact(int $teamId, int $contactId): void
    {
        $channelIds = [];
        DB::transaction(function () use ($teamId, $contactId, &$channelIds): void {
            $contact = Contact::withoutGlobalScopes()
                ->where('team_id', $teamId)
                ->whereKey($contactId)
                ->lockForUpdate()
                ->first();
            if ($contact === null) {
                return;
            }

            $integration = Integration::withoutGlobalScopes()
                ->where('team_id', $teamId)
                ->where('type', 'crm')
                ->whereNull('deleted_at')
                ->first();
            $commercialStageIds = collect(data_get($integration?->crm_settings, 'commercial_stage_ids', []))
                ->filter(fn (mixed $id): bool => is_string($id) && $id !== '')
                ->values()
                ->all();
            $hasCommercialDeal = $integration !== null && $commercialStageIds !== [] && CrmLinkedDeal::withoutGlobalScopes()
                ->where('team_id', $teamId)
                ->where('integration_id', $integration->getKey())
                ->where('status', 'open')
                ->whereIn('stage_id', $commercialStageIds)
                ->whereIn('crm_contact_link_id', CrmContactLink::withoutGlobalScopes()
                    ->where('team_id', $teamId)
                    ->where('contact_id', $contactId)
                    ->select('id'))
                ->exists();

            $wasPausedByCrm = $contact->crm_agent_paused;
            $wasSuppressed = $contact->crm_pause_suppressed;
            $isSuppressed = $hasCommercialDeal && $wasSuppressed;
            $isPausedByCrm = $hasCommercialDeal && ! $isSuppressed;
            $attributes = [
                'crm_agent_paused' => $isPausedByCrm,
                'crm_pause_suppressed' => $isSuppressed,
            ];

            if ($isPausedByCrm && ! $contact->human_waiting) {
                $attributes['human_waiting'] = true;
                $attributes['human_waiting_at'] = now();
            } elseif (! $isPausedByCrm && $wasPausedByCrm && ! $this->hasWaitingFlow($teamId, $contactId)) {
                $attributes['human_waiting'] = false;
                $attributes['human_waiting_at'] = null;
            }

            if ($wasPausedByCrm === $isPausedByCrm
                && $wasSuppressed === $isSuppressed
                && ! array_key_exists('human_waiting', $attributes)) {
                return;
            }

            $contact->forceFill($attributes)->save();

            if ($wasPausedByCrm !== $isPausedByCrm) {
                HumanConversationUpdated::dispatch($teamId, $contactId, null, $isPausedByCrm ? 'crm_paused' : 'crm_returned');
            }

            if ($wasPausedByCrm && ! $isPausedByCrm) {
                $channelIds = $this->pendingMessageChannelIds($teamId, $contactId);
            }

            if (! $wasPausedByCrm && $isPausedByCrm) {
                CrmRealtimeNotification::dispatch(
                    $teamId,
                    $contactId,
                    'crm_paused',
                    'IA pausada pelo CRM para '.($contact->name ?: $contact->phone).'.',
                );
            }
        });

        foreach ($channelIds as $channelId) {
            RespondToWhatsAppMessage::dispatch((string) $channelId, $contactId)->afterCommit();
        }
    }

    public function reconcileDealChange(CrmLinkedDeal $linkedDeal, ?string $previousStatus, ?string $lossReasonId = null): void
    {
        $linkedDeal->loadMissing('integration');
        $contactId = CrmContactLink::withoutGlobalScopes()
            ->where('team_id', $linkedDeal->team_id)
            ->whereKey($linkedDeal->crm_contact_link_id)
            ->value('contact_id');
        $contact = $contactId === null ? null : Contact::withoutGlobalScopes()
            ->where('team_id', $linkedDeal->team_id)
            ->whereKey($contactId)
            ->first();
        if ($contact === null) {
            return;
        }

        $this->reconcileContact($linkedDeal->team_id, (int) $contact->getKey());

        if ($previousStatus === $linkedDeal->status || ! in_array($linkedDeal->status, ['won', 'lost'], true)) {
            return;
        }

        $type = $linkedDeal->status === 'won' ? 'deal_won' : 'deal_lost';
        $message = 'Negócio vinculado de '.($contact->name ?: $contact->phone).' foi '.($linkedDeal->status === 'won' ? 'ganho' : 'perdido').' no RD Station CRM.';
        if ($linkedDeal->status === 'lost') {
            $reasons = data_get($linkedDeal->integration?->crm_metadata, 'lost_reasons', []);
            $reasonData = collect($reasons)->first(
                fn (array $reason): bool => (string) ($reason['id'] ?? '') === (string) $lossReasonId,
            );
            $reasonName = is_array($reasonData) ? ($reasonData['name'] ?? null) : null;
            $reason = is_string($reasonName) && $reasonName !== '' ? $reasonName : $lossReasonId;
            $message .= ' Motivo de perda: '.(is_string($reason) && $reason !== '' ? $reason : 'não informado no registro recebido do CRM').'.';
        }

        CrmRealtimeNotification::dispatch($linkedDeal->team_id, (int) $contact->getKey(), $type, $message);
    }

    public function suppressForManualReturn(Contact $contact): void
    {
        $channelIds = [];
        $changed = DB::transaction(function () use ($contact, &$channelIds): bool {
            $lockedContact = Contact::withoutGlobalScopes()
                ->where('team_id', $contact->team_id)
                ->whereKey($contact->getKey())
                ->lockForUpdate()
                ->first();
            if ($lockedContact === null || ! $lockedContact->crm_agent_paused) {
                return false;
            }

            $channelIds = $this->pendingMessageChannelIds($contact->team_id, (int) $contact->getKey());

            $attributes = ['crm_agent_paused' => false, 'crm_pause_suppressed' => true];
            if (! $this->hasWaitingFlow($contact->team_id, (int) $contact->getKey())) {
                $attributes['human_waiting'] = false;
                $attributes['human_waiting_at'] = null;
            }
            $lockedContact->forceFill($attributes)->save();
            HumanConversationUpdated::dispatch($contact->team_id, (int) $contact->getKey(), null, 'crm_returned');

            return true;
        });

        if ($changed) {
            foreach ($channelIds as $channelId) {
                RespondToWhatsAppMessage::dispatch((string) $channelId, (int) $contact->getKey())->afterCommit();
            }
        }
    }

    private function hasWaitingFlow(int $teamId, int $contactId): bool
    {
        return FlowExecution::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->where('contact_id', $contactId)
            ->where('status', 'paused')
            ->where('waiting_for', 'human')
            ->exists();
    }

    private function pendingMessageChannelIds(int $teamId, int $contactId): array
    {
        return Channel::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->whereIn('id', function ($query) use ($teamId, $contactId): void {
                $query->select('channel_id')
                    ->from('messages')
                    ->where('team_id', $teamId)
                    ->where('contact_id', $contactId)
                    ->where('direction', 'incoming')
                    ->where('is_responded', false)
                    ->distinct();
            })
            ->pluck('id')
            ->all();
    }
}
