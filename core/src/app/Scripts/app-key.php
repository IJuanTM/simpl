<?php

declare(strict_types=1);

use app\Utils\Console;
use Random\RandomException;

// Runs from Composer's post-install hook, before the config bootstrap exists, so it cannot use start.php.
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

$fresh = in_array('--fresh', $_SERVER['argv'] ?? [], true);

$envPath = dirname(__DIR__, 2) . '/.env';

if (!is_file($envPath)) {
    if (!$fresh) return;
    Console::fail("No .env file found at $envPath");
}

$env = file_get_contents($envPath);
$hasLine = preg_match('/^APP_KEY=(.*)$/m', $env, $match);
$value = $hasLine ? trim($match[1], " \t\"'") : '';

if ($value !== '' && $value !== '@app-key' && !$fresh) return;

try {
    $key = bin2hex(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
} catch (RandomException $e) {
    Console::fail('Could not generate a random key: ' . $e->getMessage());
}

$env = $hasLine
    ? preg_replace('/^APP_KEY=.*$/m', "APP_KEY=$key", $env)
    : rtrim($env, "\r\n") . "\nAPP_KEY=$key\n";

if (file_put_contents($envPath, $env) === false) Console::fail('Could not write APP_KEY to .env');

Console::box('Application key');
Console::line();
Console::success($fresh ? 'APP_KEY regenerated in .env' : 'APP_KEY written to .env', true);
Console::line();
