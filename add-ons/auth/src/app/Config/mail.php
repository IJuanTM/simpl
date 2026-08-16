<?php

declare(strict_types=1);

define('MAIL_CONFIG', [
    'site_address' => $_ENV['SITE_MAIL'] ?? 'support@example.com',
    'no_reply_address' => $_ENV['NO_REPLY_MAIL'] ?? 'noreply@example.com',
    'logo_url' => $_ENV['MAIL_LOGO_URL'] ?? 'https://cdn.simpl.iwanvanderwal.nl/img/simpl-sm.png',
]);

define('SMTP_CONFIG', [
    'development' => [
        'host' => 'localhost',
        'port' => 25,
        'smtp_auth' => false,
        'encryption' => null
    ],
    'production' => [
        'host' => $_ENV['SMTP_HOST'],
        'port' => $_ENV['SMTP_PORT'] ?? 587,
        'smtp_auth' => true,
        'encryption' => 'tls',                // null, 'tls', or 'ssl'
        'username' => $_ENV['SMTP_USERNAME'],
        'password' => $_ENV['SMTP_PASSWORD']
    ]
]);
