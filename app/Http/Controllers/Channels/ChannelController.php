<?php

namespace App\Http\Controllers\Channels;

use App\Enums\ChannelType;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Channel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ChannelController extends Controller
{
    /**
     * Display the Channels the Empresa has configured.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $team = $user?->company();

        abort_if($team === null, 404);
        Gate::authorize('viewAny', Channel::class);

        $canManage = $user->can('create', Channel::class);

        $channels = $team->channels()
            ->with([
                'agent:id,name',
                'whatsappPhoneNumber:id,channel_id,display_phone_number,verified_name',
            ])
            ->orderBy('id')
            ->get();

        $catalog = ChannelType::catalog($user);
        $configuredTypes = $channels->map(fn (Channel $channel): string => $channel->type->value)->all();

        return Inertia::render('channels/index', [
            'channels' => $channels->map(fn (Channel $channel): array => $this->toFrontend($channel, $catalog, $canManage))->values(),
            'channelTypes' => array_map(fn (array $channelType): array => [
                ...$channelType,
                'configured' => in_array($channelType['value'], $configuredTypes, true),
            ], $catalog),
            'agents' => $team->agents()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Agent $agent): array => [
                    'id' => $agent->id,
                    'name' => $agent->name,
                ])
                ->values(),
            'permissions' => $user->toTeamPermissions($team),
        ]);
    }

    /**
     * Serialize a Channel for the listing card.
     *
     * @param  array<int, array{value: string, label: string, description: string, icon: string, available: bool, destination: string|null}>  $catalog
     * @return array<string, mixed>
     */
    private function toFrontend(Channel $channel, array $catalog, bool $canManage): array
    {
        $channelType = collect($catalog)->firstWhere('value', $channel->type->value);
        $phoneNumber = $channel->whatsappPhoneNumber;

        return [
            'id' => $channel->id,
            'label' => $channelType['label'] ?? $channel->type->label(),
            'description' => $channelType['description'] ?? $channel->type->description(),
            'icon' => $channelType['icon'] ?? $channel->type->icon(),
            'destination' => $canManage ? $channel->type->destination() : null,
            'status' => $channel->status->value,
            'statusLabel' => $channel->status->label(),
            'lastError' => $channel->last_error,
            'displayPhoneNumber' => $phoneNumber?->display_phone_number,
            'verifiedName' => $phoneNumber?->verified_name,
            'agent' => $channel->agent === null ? null : [
                'id' => $channel->agent->id,
                'name' => $channel->agent->name,
            ],
        ];
    }
}
