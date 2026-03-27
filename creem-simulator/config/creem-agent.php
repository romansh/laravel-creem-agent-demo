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

    // Other agent settings can go here; this file mirrors the published
    // configuration the package will provide. If you run `php artisan
    // vendor:publish`, verify these values in your application's
    // `config/creem-agent.php` and adjust as needed.
];
