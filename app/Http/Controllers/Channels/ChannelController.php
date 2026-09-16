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
                'whatsappPhoneNumber:id,channel_id,display_phone_number',
            ])
            ->get()
            ->keyBy(fn (Channel $channel): string => $channel->type->value);
        $canManage = $user->can('create', Channel::class);

        $catalog = array_map(function (array $channelType) use ($configuredChannels, $canManage): array {
            /** @var Channel|null $channel */
            $channel = $configuredChannels->get($channelType['value']);
            $status = $channel?->status;

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
                'displayPhoneNumber' => $channel?->whatsappPhoneNumber?->display_phone_number,
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
