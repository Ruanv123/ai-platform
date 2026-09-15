<?php

use Illuminate\Support\Facades\Route;

test('verification notification endpoint is unavailable when email verification is disabled', function () {
    expect(Route::has('verification.send'))->toBeFalse();
});
