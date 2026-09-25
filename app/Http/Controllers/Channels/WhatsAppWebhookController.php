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
            if (! is_array($change) || ($change['field'] ?? null) !== 'messages') {
                continue;
            }

            $value = $change['value'] ?? [];

            if (! is_array($value)) {
                continue;
            }

            $phoneNumberId = data_get($value, 'metadata.phone_number_id');

            if (! is_scalar($phoneNumberId) || blank($phoneNumberId)) {
                continue;
            }

            $phoneNumber = WhatsAppPhoneNumber::query()
                ->where('whatsapp_account_id', $account->getKey())
                ->where('phone_number_id', (string) $phoneNumberId)
                ->with('channel')
                ->first();
            $channel = $phoneNumber?->channel;

            if ($channel === null || $channel->type !== ChannelType::WhatsApp) {
                continue;
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
