<?php

namespace App\Http\Responses;

use App\Enums\ChannelStatus;
use App\Exceptions\WhatsAppProfileException;
use App\Models\Channel;
use App\WhatsApp\Profiles\WhatsAppProfileProvider;
use Illuminate\Http\RedirectResponse;
use Inertia\DeferProp;
use Inertia\Inertia;

final class WhatsAppProfileResponse
{
    public static function deferredProfile(
        ?Channel $channel,
        WhatsAppProfileProvider $profileProvider,
    ): DeferProp {
        return Inertia::defer(function () use ($channel, $profileProvider): array {
            if ($channel === null || $channel->status !== ChannelStatus::Connected) {
                return ['state' => 'not_connected'];
            }

            try {
                return [
                    'state' => 'ready',
                    'data' => $profileProvider->read($channel)->toArray(),
                ];
            } catch (WhatsAppProfileException $exception) {
                self::flashError($exception->getMessage());

                return [
                    'state' => 'error',
                    'message' => $exception->getMessage(),
                ];
            }
        });
    }

    public static function updateSucceeded(string $routeName, string $message): RedirectResponse
    {
        return self::redirectToProfile($routeName, 'success', $message);
    }

    public static function updateFailed(string $routeName, string $message): RedirectResponse
    {
        return self::redirectToProfile($routeName, 'error', $message);
    }

    private static function flashError(string $message): void
    {
        Inertia::flash('toast', [
            'type' => 'error',
            'message' => $message,
        ]);
    }

    private static function redirectToProfile(
        string $routeName,
        string $type,
        string $message,
    ): RedirectResponse {
        Inertia::flash('toast', [
            'type' => $type,
            'message' => $message,
        ]);

        return to_route($routeName, ['tab' => 'perfil']);
    }
}
