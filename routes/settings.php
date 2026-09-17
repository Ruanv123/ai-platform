<?php

use App\Http\Controllers\Settings\BusinessHoursController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Teams\TeamController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Controllers\Teams\TeamMemberController;
use App\Http\Middleware\EnsureUserHasTeam;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', EnsureUserHasTeam::class])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', EnsureUserHasTeam::class])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');

    Route::get('settings/teams', [TeamController::class, 'edit'])->name('teams.edit');
    Route::patch('settings/teams', [TeamController::class, 'update'])->name('teams.update');
    Route::get('settings/company', [TeamController::class, 'edit'])->name('company.edit');
    Route::patch('settings/company', [TeamController::class, 'update'])->name('company.update');
    Route::get('settings/business-hours', [BusinessHoursController::class, 'edit'])->name('business-hours.edit');
    Route::patch('settings/business-hours', [BusinessHoursController::class, 'update'])->name('business-hours.update');
    Route::patch('settings/teams/members/{user}', [TeamMemberController::class, 'update'])->name('teams.members.update');
    Route::delete('settings/teams/members/{user}', [TeamMemberController::class, 'destroy'])->name('teams.members.destroy');
    Route::delete('settings/teams/leave', [TeamMemberController::class, 'leave'])->name('teams.leave');
    Route::post('settings/teams/invitations', [TeamInvitationController::class, 'store'])->name('teams.invitations.store');
    Route::delete('settings/teams/invitations/{invitation}', [TeamInvitationController::class, 'destroy'])->name('teams.invitations.destroy');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
