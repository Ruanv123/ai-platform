<?php

use App\Models\TeamInvitation;
use Illuminate\Support\Facades\Schedule;

Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command('agents:resume-expired')->everyMinute()->withoutOverlapping();
Schedule::command('agents:send-appointment-reminders')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('agents:send-follow-ups')->everyTenMinutes()->withoutOverlapping();

Schedule::call(function () {
    TeamInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired team invitations');
