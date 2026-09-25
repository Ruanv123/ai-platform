<?php

namespace App\Http\Controllers\Channels;

use App\Enums\ChannelType;
use App\Enums\WhatsAppConnectionMethod;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessWhatsAppWebhook;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WhatsAppWebhookController extends Controller
{
    public function handleTechProvider(Request $request): Response
    {
        return $request->isMethod('GET')
            ? $this->verifyTechProvider($request)
            : $this->receiveTechProvider($request);
    }

    public function handle(Request $request): Response
    {
        return $request->isMethod('GET')
            ? $this->verify($request)
            : $this->receive($request);
    }

    private function verifyTechProvider(Request $request): Response
    {
        $mode = $this->queryParameter($request, 'hub.mode');
        $verifyToken = $this->queryParameter($request, 'hub.verify_token');
        $challenge = $this->queryParameter($request, 'hub.challenge');
        $configuredVerifyToken = config('services.whatsapp.platform.webhook_verify_token');

        if ($mode !== 'subscribe'
            || ! is_string($configuredVerifyToken)
            || blank($configuredVerifyToken)
            || blank($verifyToken)
            || blank($challenge)
            || ! hash_equals($configuredVerifyToken, $verifyToken)) {
            return response('Forbidden', 403);
        }

        return response($challenge, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    private function verify(Request $request): Response
    {
        $mode = $this->queryParameter($request, 'hub.mode');
        $verifyToken = $this->queryParameter($request, 'hub.verify_token');
        $challenge = $this->queryParameter($request, 'hub.challenge');

        if ($mode !== 'subscribe' || blank($verifyToken) || blank($challenge)) {
            return response('Forbidden', 403);
        }

        $account = WhatsAppAccount::query()
            ->where('connection_method', WhatsAppConnectionMethod::OwnApp)
            ->where('verify_token', $verifyToken)
            ->first();

        if ($account === null) {
            return response('Forbidden', 403);
        }

        $account->update([
            'webhook_verified_at' => now(),
        ]);

        return response($challenge, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    private function receive(Request $request): Response
    {
        $rawBody = $request->getContent();
        $signature = $request->header('X-Hub-Signature-256');

        if (! is_string($signature) || blank($signature)) {
            return response('Forbidden', 403);
        }

        $decodedPayload = json_decode($rawBody, true);
        $payload = is_array($decodedPayload) ? $decodedPayload : [];
        $entries = is_array($payload['entry'] ?? null)
            ? $payload['entry']
            : [];
        $wabaIds = $this->wabaIds($entries);
        $accounts = $wabaIds === []
            ? new Collection
            : WhatsAppAccount::query()
                ->where('connection_method', WhatsAppConnectionMethod::OwnApp)
                ->whereNotNull('app_secret')
                ->whereIn('waba_id', $wabaIds)
                ->get(['id', 'waba_id', 'app_secret'])
                ->keyBy('waba_id');
        $validEntries = [];

        foreach ($entries as $entryIndex => $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $wabaId = $entry['id'] ?? null;
            $account = is_scalar($wabaId) ? $accounts->get((string) $wabaId) : null;

            if ($account instanceof WhatsAppAccount
                && $this->signatureMatches($rawBody, $account->app_secret, $signature)) {
                $validEntries[$entryIndex] = $account;
            }
        }

        if ($validEntries === []) {
            if ($accounts->isNotEmpty() || ! $this->hasValidOwnAppSignature($rawBody, $signature)) {
                return response('Forbidden', 403);
            }

            return response('EVENT_RECEIVED');
        }

        foreach ($validEntries as $entryIndex => $account) {
            $entry = $entries[$entryIndex];
            $this->dispatchEntryEvents($account, $entry, $payload);
        }

        return response('EVENT_RECEIVED');
    }

    private function receiveTechProvider(Request $request): Response
    {
        $rawBody = $request->getContent();
        $signature = $request->header('X-Hub-Signature-256');
        $appSecret = config('services.whatsapp.platform.app_secret');

        if (! is_string($signature)
            || blank($signature)
            || ! is_string($appSecret)
            || ! $this->signatureMatches($rawBody, $appSecret, $signature)) {
            return response('Forbidden', 403);
        }

        $decodedPayload = json_decode($rawBody, true);
        $payload = is_array($decodedPayload) ? $decodedPayload : [];
        $entries = is_array($payload['entry'] ?? null)
            ? $payload['entry']
            : [];
        $wabaIds = $this->wabaIds($entries);
        $accounts = $wabaIds === []
            ? new Collection
            : WhatsAppAccount::query()
                ->where('connection_method', WhatsAppConnectionMethod::TechProvider)
                ->whereIn('waba_id', $wabaIds)
                ->get(['id', 'waba_id'])
                ->keyBy('waba_id');

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $wabaId = $entry['id'] ?? null;
            $account = is_scalar($wabaId) ? $accounts->get((string) $wabaId) : null;

            if ($account instanceof WhatsAppAccount) {
                $this->dispatchEntryEvents($account, $entry, $payload);
            }
        }

        return response('EVENT_RECEIVED');
    }

    private function dispatchEntryEvents(WhatsAppAccount $account, array $entry, array $payload): void
    {
        $changes = $entry['changes'] ?? [];

        if (! is_array($changes)) {
            return;
        }

        foreach ($changes as $change) {
            if (! is_array($change)) {
                continue;
            }

            $field = $change['field'] ?? null;
            $value = $change['value'] ?? [];

            if (! is_array($value)) {
                continue;
            }

            if ($field === 'messages') {
                $this->dispatchMessageEvents($account, $entry, $payload, $value);

                continue;
            }

            if (is_string($field) && in_array($field, [
                'message_template_status_update',
                'template_category_update',
                'message_template_quality_update',
            ], true)) {
                $this->dispatchTemplateEvent($account, $entry, $payload, $field, $value);

                continue;
            }

            if (! in_array($field, [
                'account_update',
                'account_review_update',
                'phone_number_quality_update',
                'phone_number_name_update',
            ], true)) {
                continue;
            }

            $eventPayload = $payload;
            $eventPayload['entry'] = [[
                'id' => $entry['id'] ?? $account->waba_id,
                'changes' => [$change],
            ]];

            foreach ($this->channelsForHealthEvent($account, (string) $field, $value) as $channel) {
                ProcessWhatsAppWebhook::dispatch($channel->getKey(), $eventPayload);
            }
        }
    }

    private function dispatchMessageEvents(WhatsAppAccount $account, array $entry, array $payload, array $value): void
    {
        $phoneNumberId = data_get($value, 'metadata.phone_number_id');

        if (! is_scalar($phoneNumberId) || blank($phoneNumberId)) {
            return;
        }

        $phoneNumber = WhatsAppPhoneNumber::query()
            ->where('whatsapp_account_id', $account->getKey())
            ->where('phone_number_id', (string) $phoneNumberId)
            ->with('channel')
            ->first();
        $channel = $phoneNumber?->channel;

        if ($channel === null || $channel->type !== ChannelType::WhatsApp) {
            return;
        }

        foreach (['messages', 'statuses'] as $itemsKey) {
            $items = $value[$itemsKey] ?? [];

            if (! is_array($items)) {
                continue;
            }

            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $itemValue = array_merge($value, [
                    'messages' => [],
                    'statuses' => [],
                    $itemsKey => [$item],
                ]);
                $itemPayload = $payload;
                $itemPayload['entry'] = [[
                    'id' => $entry['id'],
                    'changes' => [[
                        'field' => 'messages',
                        'value' => $itemValue,
                    ]],
                ]];

                ProcessWhatsAppWebhook::dispatch($channel->getKey(), $itemPayload);
            }
        }
    }

    private function channelsForHealthEvent(WhatsAppAccount $account, string $field, array $value): array
    {
        $phoneNumber = $this->phoneNumberForHealthEvent($account, $value);

        if ($phoneNumber?->channel !== null && $phoneNumber->channel->type === ChannelType::WhatsApp) {
            return [$phoneNumber->channel];
        }

        if (! in_array($field, ['account_update', 'account_review_update'], true)) {
            return [];
        }

        return $account->phoneNumbers()
            ->with('channel')
            ->get()
            ->pluck('channel')
            ->filter(fn ($channel): bool => $channel !== null && $channel->type === ChannelType::WhatsApp)
            ->values()
            ->all();
    }

    private function phoneNumberForHealthEvent(WhatsAppAccount $account, array $value): ?WhatsAppPhoneNumber
    {
        $phoneNumberId = $value['phone_number_id'] ?? data_get($value, 'metadata.phone_number_id');
        $phoneValues = array_filter([
            $value['display_phone_number'] ?? null,
            $value['phone_number'] ?? null,
            $value['business_phone'] ?? null,
        ], fn (mixed $number): bool => is_scalar($number) && filled($number));
        $normalizedPhoneNumbers = array_map(
            fn (mixed $number): string => (string) preg_replace('/\D+/', '', (string) $number),
            $phoneValues,
        );

        return $account->phoneNumbers()
            ->with('channel')
            ->get()
            ->first(function (WhatsAppPhoneNumber $phoneNumber) use ($phoneNumberId, $normalizedPhoneNumbers): bool {
                if (is_scalar($phoneNumberId) && (string) $phoneNumberId === $phoneNumber->phone_number_id) {
                    return true;
                }

                $storedPhoneNumber = (string) preg_replace('/\D+/', '', (string) $phoneNumber->display_phone_number);

                return $storedPhoneNumber !== '' && in_array($storedPhoneNumber, $normalizedPhoneNumbers, true);
            });
    }

    private function dispatchTemplateEvent(
        WhatsAppAccount $account,
        array $entry,
        array $payload,
        string $field,
        array $value,
    ): void {
        $phoneNumber = WhatsAppPhoneNumber::query()
            ->where('whatsapp_account_id', $account->getKey())
            ->whereHas('channel', fn ($query) => $query->where('type', ChannelType::WhatsApp))
            ->with('channel')
            ->first();
        $channel = $phoneNumber?->channel;

        if ($channel === null || $channel->type !== ChannelType::WhatsApp) {
            return;
        }

        $itemPayload = $payload;
        $itemPayload['entry'] = [[
            'id' => $entry['id'],
            'changes' => [[
                'field' => $field,
                'value' => $value,
            ]],
        ]];

        ProcessWhatsAppWebhook::dispatch($channel->getKey(), $itemPayload);
    }

    private function hasValidOwnAppSignature(string $rawBody, string $signature): bool
    {
        return WhatsAppAccount::query()
            ->where('connection_method', WhatsAppConnectionMethod::OwnApp)
            ->whereNotNull('app_secret')
            ->get(['app_secret'])
            ->contains(fn (WhatsAppAccount $account): bool => $this->signatureMatches(
                $rawBody,
                $account->app_secret,
                $signature,
            ));
    }

    private function signatureMatches(string $rawBody, ?string $appSecret, string $signature): bool
    {
        if (blank($appSecret) || ! str_starts_with($signature, 'sha256=')) {
            return false;
        }

        return hash_equals(
            'sha256='.hash_hmac('sha256', $rawBody, $appSecret),
            $signature,
        );
    }

    private function wabaIds(array $entries): array
    {
        $wabaIds = [];

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $wabaId = $entry['id'] ?? null;

            if (is_scalar($wabaId) && filled($wabaId)) {
                $wabaIds[] = (string) $wabaId;
            }
        }

        return array_values(array_unique($wabaIds));
    }

    private function queryParameter(Request $request, string $name): ?string
    {
        $value = $request->query($name) ?? $request->query(str_replace('.', '_', $name));

        return is_scalar($value) ? (string) $value : null;
    }
}
