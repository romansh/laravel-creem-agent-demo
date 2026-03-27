<?php

return [
    'profiles' => [
        'default' => [
            'api_key' => env('CREEM_API_KEY'),
            'test_mode' => env('CREEM_TEST_MODE', true),
            'webhook_secret' => env('CREEM_WEBHOOK_SECRET'),
        ],

        'secondary' => [
            'api_key' => env('CREEM_SECONDARY_API_KEY'),
            'test_mode' => env('CREEM_TEST_MODE', true),
            'webhook_secret' => env('CREEM_SECONDARY_WEBHOOK_SECRET'),
        ],
    ],

    'test_api_url' => env('CREEM_TEST_API_URL', 'https://test-api.creem.io/v1'),
    'api_url' => env('CREEM_API_URL', 'https://api.creem.io/v1'),

    'webhook' => [
        'path' => '/creem/webhook',
        'middleware' => ['api'],
    ],

    'http' => [
        'timeout' => 30,
        'retry' => [
            'times' => 3,
            'sleep' => 100,
        ],
    ],
];
