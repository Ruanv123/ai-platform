<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'whatsapp' => [
        'graph_api_version' => env('WHATSAPP_GRAPH_API_VERSION', 'v23.0'),
        'graph_api_url' => env('WHATSAPP_GRAPH_API_URL', 'https://graph.facebook.com'),
        'platform' => [
            'app_id' => env('WHATSAPP_PLATFORM_APP_ID'),
            'app_secret' => env('WHATSAPP_PLATFORM_APP_SECRET'),
            'config_id' => env('WHATSAPP_PLATFORM_CONFIG_ID'),
            'webhook_verify_token' => env('WHATSAPP_PLATFORM_WEBHOOK_VERIFY_TOKEN'),
        ],
    ],

    'evolution' => [
        'base_url' => env('EVOLUTION_API_URL'),
        'api_key' => env('EVOLUTION_API_KEY'),
        'webhook_url' => env('EVOLUTION_WEBHOOK_URL'),
        'test_allowed_phones' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('EVOLUTION_TEST_ALLOWED_PHONES', '')),
        ))),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL').'/integrations/agenda/google/callback'),
    ],

];
