<?php

namespace App\WhatsApp\Oficial;

use App\Enums\ChannelStatus;
use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\WhatsAppAccount;
use Illuminate\Http\Client\Response;

final class MetaGraphApiResponseHandler
{
    public function markChannelUnavailableForInvalidToken(Response $response, WhatsAppAccount|Channel $resource): void
    {
        if ((int) $response->json('error.code') !== 190) {
            return;
        }

        $error = $response->json('error');
        $errorMessage = (new MetaErrorTranslator)->translate(is_array($error) ? $error : null);
        $account = $resource instanceof Channel
            ? $resource->whatsappPhoneNumber()->with('whatsappAccount')->first()?->whatsappAccount
            : $resource;

        if ($account === null) {
            if ($resource instanceof Channel && $resource->type === ChannelType::WhatsApp) {
                $resource->update([
                    'status' => ChannelStatus::Error,
                    'last_error' => $errorMessage,
                ]);
            }

            return;
        }

        $channels = $account->phoneNumbers()
            ->with('channel')
            ->get()
            ->pluck('channel')
            ->filter(fn (?Channel $channel): bool => $channel instanceof Channel && $channel->type === ChannelType::WhatsApp);

        foreach ($channels as $channel) {
            $channel->update([
                'status' => ChannelStatus::Error,
                'last_error' => $errorMessage,
            ]);
        }
    }
}
