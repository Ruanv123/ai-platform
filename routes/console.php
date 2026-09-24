<?php

use App\Models\TeamInvitation;
use Illuminate\Support\Facades\Schedule;

Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command('flows:reconcile')->everyMinute()->withoutOverlapping();
Schedule::command('flows:prune-traces')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('flows:prune-event-deliveries')->dailyAt('02:30')->withoutOverlapping();
Schedule::command('inbox:release-stale')->everyMinute()->withoutOverlapping();
Schedule::command('agents:resume-expired')->everyMinute()->withoutOverlapping();

Schedule::call(function () {
    TeamInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired team invitations');
