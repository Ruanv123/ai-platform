<?php

namespace App\Crm;

use App\Enums\ChannelType;
use App\Enums\MessageDirection;
use App\Jobs\Integrations\ProcessCrmOperation;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\CrmContactLink;
use App\Models\CrmOperation;
use App\Models\Integration;
use Illuminate\Support\Facades\Cache;

class CrmAttendedContactRegistrationDispatcher
{
    public function afterSuccessfulResponse(Channel $channel, Contact $contact): ?CrmOperation
    {
        if ($channel->type !== ChannelType::WhatsApp || $channel->team_id !== $contact->team_id) {
            return null;
        }

        $integration = $channel->team->integrations()
            ->where('type', 'crm')
            ->where('status', 'connected')
            ->first();
        if (! $integration instanceof Integration
            || data_get($integration->crm_settings, 'register_attended_contacts') !== true) {
            return null;
        }

        $minimumMessages = data_get($integration->crm_settings, 'minimum_attended_contact_messages', 2);
        if (! is_int($minimumMessages) || $minimumMessages < 1) {
            return null;
        }

        $operation = Cache::lock('crm:attended-contact-registration:'.$contact->team_id.':'.$contact->getKey(), 120)
            ->block(30, function () use ($contact, $integration, $minimumMessages): ?CrmOperation {
                $contactLink = CrmContactLink::query()
                    ->where('team_id', $contact->team_id)
                    ->where('contact_id', $contact->getKey())
                    ->where('integration_id', $integration->getKey())
                    ->exists();
                if ($contactLink) {
                    return null;
                }

                $attendedMessageCount = $contact->messages()
                    ->where('direction', MessageDirection::Incoming)
                    ->where('is_responded', true)
                    ->whereHas('channel', fn ($query) => $query->where('type', '!=', ChannelType::WhatsAppTest->value))
                    ->count();
                if ($attendedMessageCount < $minimumMessages) {
                    return null;
                }

                $hasActiveRegistration = CrmOperation::query()
                    ->where('team_id', $contact->team_id)
                    ->where('integration_id', $integration->getKey())
                    ->where('contact_id', $contact->getKey())
                    ->where('type', 'ensure_person')
                    ->whereIn('status', ['pending', 'processing'])
                    ->exists();
                if ($hasActiveRegistration) {
                    return null;
                }

                $defaultOwnerId = data_get($integration->crm_settings, 'default_owner_id');

                return CrmOperation::query()->create([
                    'team_id' => $contact->team_id,
                    'integration_id' => $integration->getKey(),
                    'contact_id' => $contact->getKey(),
                    'origin' => 'automatic',
                    'type' => 'ensure_person',
                    'status' => 'pending',
                    'payload' => [
                        'default_owner_id' => is_string($defaultOwnerId) ? $defaultOwnerId : null,
                    ],
                    'summary' => ['action' => 'register_attended_contact'],
                    'expires_at' => now()->addDays(30),
                ]);
            });

        if ($operation instanceof CrmOperation) {
            ProcessCrmOperation::dispatch($operation->getKey())
                ->onQueue('crm-operations')
                ->afterCommit();
        }

        return $operation instanceof CrmOperation ? $operation : null;
    }
}
