<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Knowledge\CollectionController;
use App\Http\Controllers\Knowledge\DocumentController;
use App\Http\Controllers\Onboarding\CompanyController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureUserHasTeam;
use App\Http\Middleware\RedirectIfUserHasTeam;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', RedirectIfUserHasTeam::class])->group(function () {
    Route::get('onboarding/company', [CompanyController::class, 'create'])->name('onboarding.company');
    Route::post('onboarding/company', [CompanyController::class, 'store'])->name('onboarding.company.store');
});

Route::get('dashboard', DashboardController::class)
    ->middleware(['auth', EnsureUserHasTeam::class])
    ->name('dashboard');

Route::middleware(['auth', EnsureUserHasTeam::class])->group(function () {
    Route::resource('knowledge/collections', CollectionController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->names('collections');

    Route::get('knowledge/collections/{collection}/documents', [DocumentController::class, 'index'])
        ->name('documents.index');
    Route::post('knowledge/collections/{collection}/documents', [DocumentController::class, 'store'])
        ->name('documents.store');
    Route::patch('knowledge/documents/{document}', [DocumentController::class, 'update'])
        ->name('documents.update');
    Route::patch('knowledge/documents/{document}/move', [DocumentController::class, 'move'])
        ->name('documents.move');
    Route::post('knowledge/documents/{document}/reprocess', [DocumentController::class, 'reprocess'])
        ->name('documents.reprocess');
    Route::delete('knowledge/documents/{document}', [DocumentController::class, 'destroy'])
        ->name('documents.destroy');
});

Route::get('invitations/{invitation}', [TeamInvitationController::class, 'show'])->name('invitations.show');

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
