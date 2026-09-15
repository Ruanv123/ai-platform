<?php

namespace App\Http\Middleware;

use App\Models\TeamInvitation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasTeam
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->company() === null && ! $this->isInvitationDashboard($request)) {
            return to_route('onboarding.company');
        }

        return $next($request);
    }

    /**
     * Allow an unassigned user to access the dashboard long enough to respond to an invitation.
     */
    private function isInvitationDashboard(Request $request): bool
    {
        if ($request->routeIs('dashboard') === false || $request->user() === null) {
            return false;
        }

        $email = strtolower($request->user()->email);

        return TeamInvitation::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->exists();
    }
}
