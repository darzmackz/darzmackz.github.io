<?php

return [
    'db_host' => '127.0.0.1',
    'db_port' => 3306,
    'db_name' => 'metaxenopy',
    'db_user' => 'metaxenopy_user',
    'db_pass' => 'replace-this-password',
    'debug' => false,
    'https_only' => true,
    'encryption_key' => 'replace-with-a-long-random-secret',
    'admin_api_key' => 'replace-with-a-separate-admin-api-key',
    'view_cooldown_seconds' => 21600,
    'allowed_origins' => [
        'https://darzmackz.github.io',
        'http://localhost:4000',
    ],
    'rate_limits' => [
        'view' => ['window_seconds' => 3600, 'max_hits' => 120],
        'react' => ['window_seconds' => 3600, 'max_hits' => 40],
        'comment' => ['window_seconds' => 3600, 'max_hits' => 10],
        'inquiry' => ['window_seconds' => 3600, 'max_hits' => 8],
        'admin-update-inquiry' => ['window_seconds' => 3600, 'max_hits' => 200],
        'admin-delete-inquiry' => ['window_seconds' => 3600, 'max_hits' => 40],
        'admin-add-inquiry-comment' => ['window_seconds' => 3600, 'max_hits' => 120],
        'admin-reply-inquiry' => ['window_seconds' => 3600, 'max_hits' => 60],
    ],
    'mail' => [
        'enabled' => false,
        'from_address' => 'no-reply@example.com',
        'from_name' => 'METAXENOPY.TY',
    ],
];
