<?php

use App\Http\Controllers\Settings\BusinessHoursController;
use App\Http\Controllers\Settings\CompanyController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\SiteReadController;
use App\Http\Controllers\Settings\SiteReadProposalController;
use App\Http\Controllers\Settings\TeamController;
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

    Route::get('settings/team', [TeamController::class, 'edit'])->name('team.edit');
    Route::get('settings/company', [CompanyController::class, 'edit'])->name('company.edit');
    Route::patch('settings/company', [CompanyController::class, 'update'])->name('company.update');
    Route::post('settings/company/site-read', [SiteReadController::class, 'store'])->name('company.site-read.store');
    Route::post('settings/company/site-read-proposals/{proposal}/fields/{field}/accept', [SiteReadProposalController::class, 'acceptField'])
        ->name('company.site-read-proposals.fields.accept');
    Route::post('settings/company/site-read-proposals/{proposal}/accept-all', [SiteReadProposalController::class, 'acceptAll'])
        ->name('company.site-read-proposals.accept-all');
    Route::post('settings/company/site-read-proposals/{proposal}/discard', [SiteReadProposalController::class, 'discard'])
        ->name('company.site-read-proposals.discard');
    Route::post('settings/company/site-read/{siteRead}/agents', [SiteReadController::class, 'linkAgents'])
        ->name('company.site-read.agents');
    Route::get('settings/business-hours', [BusinessHoursController::class, 'edit'])->name('business-hours.edit');
    Route::patch('settings/business-hours', [BusinessHoursController::class, 'update'])->name('business-hours.update');
    Route::patch('settings/team/members/{user}', [TeamMemberController::class, 'update'])->name('team.members.update');
    Route::delete('settings/team/members/{user}', [TeamMemberController::class, 'destroy'])->name('team.members.destroy');
    Route::delete('settings/team/leave', [TeamMemberController::class, 'leave'])->name('team.leave');
    Route::post('settings/team/invitations', [TeamInvitationController::class, 'store'])->name('team.invitations.store');
    Route::delete('settings/team/invitations/{invitation}', [TeamInvitationController::class, 'destroy'])->name('team.invitations.destroy');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
