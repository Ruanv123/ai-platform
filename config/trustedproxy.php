<?php

$defaultTrustedProxies = env('APP_ENV') === 'local'
    ? '127.0.0.1,::1'
    : null;

return [
    'proxies' => env('TRUSTED_PROXIES', $defaultTrustedProxies),
];
