<?php

// Mail configuration
const SITE_MAIL = 'support@example.com';
const NO_REPLY_MAIL = 'noreply@example.com';

// SMTP configuration
const SMTP_CONFIG = [
    'development' => [
        'host' => 'localhost',
        'port' => 25,
        'smtp_auth' => false
    ],
    'production' => [
        'host' => 'smtp.example.com',
        'port' => 587,
        'smtp_auth' => true,
        'username' => 'username@example.com',
        'password' => 'password'
    ]
];
