<?php

return [
    'stores' => [
        'default' => [
            'profile' => 'default',
            'heartbeat_frequency' => 4, // hours
            'notifications' => ['telegram', 'database'],
        ],

        'secondary' => [
            'profile' => 'secondary',
            'heartbeat_frequency' => 8,
            'notifications' => ['telegram', 'database'],
        ],
    ],

    'llm' => [
        'enabled' => env('CREEM_AGENT_LLM_ENABLED', true),
        'provider' => env('CREEM_AGENT_LLM_PROVIDER', 'openai'),
        'model' => env('CREEM_AGENT_LLM_MODEL'),
        'timeout' => (int) env('CREEM_AGENT_LLM_TIMEOUT', 30),
        'fallback_to_rules' => env('CREEM_AGENT_LLM_FALLBACK_TO_RULES', true),
    ],

    // Other agent settings can go here; this file mirrors the published
    // configuration the package will provide. If you run `php artisan
    // vendor:publish`, verify these values in your application's
    // `config/creem-agent.php` and adjust as needed.
];
