<?php

use Illuminate\Support\Facades\Vite;

beforeEach(function (): void {
    Vite::useHotFile(storage_path('framework/testing/vite-hot-disabled'));
});

it('generates HTTPS asset URLs for requests forwarded by the local proxy', function (): void {
    config(['app.url' => 'http://localhost']);

    $response = $this->withServerVariables([
        'HTTP_HOST' => 'localhost',
        'SERVER_NAME' => 'localhost',
        'SERVER_PORT' => '80',
        'HTTPS' => 'off',
        'REMOTE_ADDR' => '127.0.0.1',
        'HTTP_X_FORWARDED_PROTO' => 'https',
    ])->get('http://localhost/login');

    $response->assertSee('https://localhost/build/assets/app-', false)
        ->assertDontSee('http://localhost/build/assets/app-', false);
});

it('ignores forwarded HTTPS headers from untrusted clients', function (): void {
    config(['app.url' => 'http://localhost']);

    $response = $this->withServerVariables([
        'HTTP_HOST' => 'localhost',
        'SERVER_NAME' => 'localhost',
        'SERVER_PORT' => '80',
        'HTTPS' => 'off',
        'REMOTE_ADDR' => '203.0.113.10',
        'HTTP_X_FORWARDED_PROTO' => 'https',
    ])->get('http://localhost/login');

    $response->assertSee('http://localhost/build/assets/app-', false)
        ->assertDontSee('https://localhost/build/assets/app-', false);
});
