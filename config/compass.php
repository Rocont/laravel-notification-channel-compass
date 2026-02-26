<?php

return [
    'default' => env('COMPASS_DEFAULT_BOT', 'main'),

    'bots' => [
        'main' => [
            'token' => env('COMPASS_BOT_TOKEN'),
        ]
    ],

    'base_url' => rtrim(env('COMPASS_BASE_URL', 'https://userbot.getcompass.com/'), '/') . '/api/v3/',

    'timeout' => 10,
];