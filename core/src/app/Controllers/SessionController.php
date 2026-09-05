<?php

declare(strict_types=1);

namespace app\Controllers;

/**
 * Controls session management operations including setting, getting, checking, and removing session data.
 */
class SessionController
{
    public function __construct()
    {
        ini_set('session.gc_maxlifetime', (string)(86400 * SESSION_LIFETIME));
        session_set_cookie_params(['lifetime' => 86400 * SESSION_LIFETIME] + AppController::secureCookieFlags());

        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    }

    /**
     * Stores a value in the session under the given key.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return void
     */
    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Returns the value stored under the given key, or null if the key is absent.
     *
     * @param string $key
     *
     * @return mixed
     */
    public static function get(string $key): mixed
    {
        return self::has($key) ? $_SESSION[$key] : null;
    }

    /**
     * Whether a value is set under the given session key.
     *
     * @param string $key
     *
     * @return bool
     */
    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    /**
     * Removes the value stored under the given session key.
     *
     * @param string $key
     *
     * @return void
     */
    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }
}
