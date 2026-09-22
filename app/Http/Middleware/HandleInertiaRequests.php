<?php

namespace App\Http\Middleware;

use App\Data\UserTeam;
use App\Enums\ChannelType;
use App\Enums\FlowExecutionStatus;
use App\Models\FlowExecution;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'currentTeam' => function () use ($user): ?UserTeam {
                $team = $user?->company();

                return $team ? $user->toUserTeam($team) : null;
            },
            'pendingHumanCount' => fn (): int => $user === null
                ? 0
                : FlowExecution::query()
                    ->where('status', FlowExecutionStatus::Paused)
                    ->where('waiting_for', 'human')
                    ->count(),
            'hasOfficialWhatsApp' => fn (): bool => $user?->company()?->channels()
                ->where('type', ChannelType::WhatsApp)
                ->whereHas('whatsappPhoneNumber.whatsappAccount')
                ->exists() ?? false,
        ];
    }
}
