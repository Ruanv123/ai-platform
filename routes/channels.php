<?php

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id): bool {
    return $user->id === $id;
});

Broadcast::channel('team.{teamId}.inbox', function (User $user, int $teamId): bool {
    $team = Team::query()->find($teamId);

    return $team !== null && $user->belongsToTeam($team);
});
