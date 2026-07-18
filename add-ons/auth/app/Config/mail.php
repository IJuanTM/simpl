<?php

declare(strict_types=1);

define('SITE_MAIL', $_ENV['SITE_MAIL'] ?? 'support@example.com');
define('NO_REPLY_MAIL', $_ENV['NO_REPLY_MAIL'] ?? 'noreply@example.com');

define('MAIL_LOGO_URL', $_ENV['MAIL_LOGO_URL'] ?? 'https://cdn.simpl.iwanvanderwal.nl/img/simpl-sm.png');

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
        'encryption' => 'tls', // null, 'tls', or 'ssl'
        'username' => $_ENV['SMTP_USERNAME'],
        'password' => $_ENV['SMTP_PASSWORD']
    ]
]);
