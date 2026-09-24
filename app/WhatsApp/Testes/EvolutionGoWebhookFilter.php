<?php

namespace App\WhatsApp\Testes;

use Illuminate\Support\Str;

final class EvolutionGoWebhookFilter
{
    /**
     * Whether a native Evolution Go webhook body is worth queueing.
     *
     * Groups, broadcast lists, statuses and channels are never answered by the Agent, and only receipts
     * from the Contact reading or playing our messages change anything.
     *
     * @param  array<string, mixed>  $payload
     */
    public function shouldProcess(array $payload): bool
    {
        $event = Str::lower((string) ($payload['event'] ?? ''));
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        return match ($event) {
            'message', 'sendmessage' => ! $this->isIgnoredChat(is_array($data['Info'] ?? null) ? $data['Info'] : []),
            'receipt' => ! $this->isIgnoredChat($data)
                && ($data['IsFromMe'] ?? false) !== true
                && in_array(Str::lower((string) ($data['Type'] ?? '')), ['read', 'played'], true),
            'connected', 'pairsuccess', 'loggedout', 'qrcode' => true,
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>  $info
     */
    private function isIgnoredChat(array $info): bool
    {
        return ($info['IsGroup'] ?? false) === true
            || Str::endsWith((string) ($info['Chat'] ?? ''), ['@g.us', '@broadcast', '@newsletter']);
    }
}
