<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

test('email verification feature is disabled', function () {
    expect(Features::enabled(Features::emailVerification()))->toBeFalse();
    expect(Route::has('verification.notice'))->toBeFalse();
    expect(Route::has('verification.verify'))->toBeFalse();
    expect(Route::has('verification.send'))->toBeFalse();
});

test('unverified users can access authenticated pages', function () {
    $user = User::factory()->unverified()->withTeam()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response->assertOk();
});
