<?php

return [
    'stores' => [
        'default' => [
            'api_key' => env('CREEM_SIMULATOR_DEFAULT_API_KEY', 'creem_test_demo_default'),
            'webhook_secret' => env('CREEM_SIMULATOR_DEFAULT_WEBHOOK_SECRET', 'whsec_demo_default'),
                'portal_base_url' => env('CREEM_SIMULATOR_PORTAL_BASE_URL', 'http://simulator:80/portal'),
        ],
        'secondary' => [
            'api_key' => env('CREEM_SIMULATOR_SECONDARY_API_KEY', 'creem_test_demo_secondary'),
            'webhook_secret' => env('CREEM_SIMULATOR_SECONDARY_WEBHOOK_SECRET', 'whsec_demo_secondary'),
            'portal_base_url' => env('CREEM_SIMULATOR_PORTAL_BASE_URL', 'http://simulator:80/portal'),
        ],
    ],

    'agent' => [
        'webhook_url' => env('CREEM_SIMULATOR_AGENT_WEBHOOK_URL'),
        'webhook_secret' => env('CREEM_SIMULATOR_AGENT_WEBHOOK_SECRET'),
        'auto_send_webhooks' => env('CREEM_SIMULATOR_AUTO_SEND_WEBHOOKS', false),
        'random_seed' => env('CREEM_SIMULATOR_RANDOM_SEED'),
    ],
];
