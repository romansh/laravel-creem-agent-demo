<?php

return [
    'default_store' => env('CREEM_AGENT_DEFAULT_STORE', 'default'),

    'stores' => [
        'default' => [
            'profile' => 'default',
            'heartbeat_frequency' => 1,
            'notifications' => ['database'],
        ],
        'secondary' => [
            'profile' => 'secondary',
            'heartbeat_frequency' => 1,
            'notifications' => ['database'],
        ],
    ],

    'state_path' => storage_path('creem-agent'),

    'notifications' => [
        'slack_webhook_url' => env('CREEM_AGENT_SLACK_WEBHOOK'),
        'telegram_bot_token' => env('CREEM_AGENT_TELEGRAM_TOKEN'),
        'telegram_chat_id' => env('CREEM_AGENT_TELEGRAM_CHAT_ID'),
        'telegram_api_base' => env('CREEM_AGENT_TELEGRAM_API_BASE', 'https://api.telegram.org'),
        'discord_webhook_url' => env('CREEM_AGENT_DISCORD_WEBHOOK'),
    ],

    'telegram' => [
        'mode' => env('CREEM_AGENT_TELEGRAM_MODE', env('CREEM_AGENT_OPENCLAW', false) ? 'openclaw' : 'laravel'),
    ],

    'quiet_hours' => [
        'start' => 23,
        'end' => 7,
        'critical_threshold' => 1000,
    ],

    'openclaw' => [
        'enabled' => env('CREEM_AGENT_OPENCLAW', false),
        'endpoint' => env('OPENCLAW_ENDPOINT'),
        'telegram' => [
            'bot_token' => env('OPENCLAW_TELEGRAM_BOT_TOKEN', env('TELEGRAM_BOT_TOKEN', env('CREEM_AGENT_TELEGRAM_TOKEN'))),
            'dm_policy' => env('OPENCLAW_TELEGRAM_DM_POLICY', 'pairing'),
            'allow_from' => env('OPENCLAW_TELEGRAM_ALLOW_FROM'),
            'group_policy' => env('OPENCLAW_TELEGRAM_GROUP_POLICY', 'allowlist'),
            'group_allow_from' => env('OPENCLAW_TELEGRAM_GROUP_ALLOW_FROM'),
            'require_mention' => env('OPENCLAW_TELEGRAM_REQUIRE_MENTION', true),
        ],
    ],

    'llm' => [
        'enabled' => env('CREEM_AGENT_LLM_ENABLED', true),
        'provider' => env('CREEM_AGENT_LLM_PROVIDER', 'openai'),
        'model' => env('CREEM_AGENT_LLM_MODEL'),
        'timeout' => (int) env('CREEM_AGENT_LLM_TIMEOUT', 30),
        'fallback_to_rules' => env('CREEM_AGENT_LLM_FALLBACK_TO_RULES', true),
    ],

    'daemon' => [
        'queue' => 'creem-agent',
        'max_time' => 3600,
    ],
];