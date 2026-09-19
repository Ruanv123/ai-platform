<?php

namespace App\Http\Controllers\Channels;

use App\Enums\ChannelStatus;
use App\Enums\ChannelType;
use App\Http\Controllers\Controller;
use App\Models\Channel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ChannelController extends Controller
{
    /**
     * Display the available channels and their current state.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $team = $user?->company();

        abort_if($team === null, 404);
        Gate::authorize('viewAny', Channel::class);

        $configuredChannels = $team->channels()
            ->with([
                'agent:id,name',
                'whatsappPhoneNumber:id,channel_id,display_phone_number,verified_name,quality_rating',
                'whatsappPhoneNumber.whatsappAccount:id,whatsapp_phone_number_id,webhook_verified_at,last_tested_at,created_at',
            ])
            ->get()
            ->keyBy(fn (Channel $channel): string => $channel->type->value);
        $canManage = $user->can('create', Channel::class);

        $catalog = array_map(function (array $channelType) use ($configuredChannels, $canManage): array {
            /** @var Channel|null $channel */
            $channel = $configuredChannels->get($channelType['value']);
            $status = $channel?->status;
            $phoneNumber = $channel?->whatsappPhoneNumber;
            $account = $phoneNumber?->whatsappAccount;

            return [
                'value' => $channelType['value'],
                'label' => $channelType['label'],
                'description' => $channelType['description'],
                'icon' => $channelType['icon'],
                'available' => $channelType['available'],
                'destination' => $channelType['destination'] === null || ! $canManage
                    ? null
                    : $channelType['destination'],
                'status' => $status?->value ?? ($channelType['value'] === ChannelType::WhatsApp->value
                    ? ChannelStatus::NotConfigured->value
                    : null),
                'statusLabel' => $status?->label() ?? ($channelType['value'] === ChannelType::WhatsApp->value
                    ? ChannelStatus::NotConfigured->label()
                    : null),
                'lastError' => $channel?->last_error,
                'displayPhoneNumber' => $phoneNumber?->display_phone_number,
                'verifiedName' => $phoneNumber?->verified_name,
                'qualityRating' => $phoneNumber?->quality_rating,
                'credentialsSavedAt' => $account?->created_at?->toIso8601String(),
                'webhookVerifiedAt' => $account?->webhook_verified_at?->toIso8601String(),
                'lastTestedAt' => $account?->last_tested_at?->toIso8601String(),
                'agent' => $channel?->agent === null ? null : [
                    'id' => $channel->agent->id,
                    'name' => $channel->agent->name,
                ],
                'agentMissing' => $status === ChannelStatus::Connected && $channel?->agent === null,
            ];
        }, ChannelType::catalog());

        return Inertia::render('channels/index', [
            'channels' => $catalog,
            'permissions' => $user->toTeamPermissions($team),
        ]);
    }
}
