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
     * Stores a value in the session associated with the specified key.
     *
     * @param string $key   The key to associate the value with.
     * @param mixed  $value The value to store in the session.
     *
     * @return void
     */
    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Retrieves the value associated with the specified key from the session.
     *
     * @param string $key The key to retrieve the value for.
     *
     * @return mixed Returns the value associated with the key if it exists, or null otherwise.
     */
    public static function get(string $key): mixed
    {
        return self::has($key) ? $_SESSION[$key] : null;
    }

    /**
     * Check if a session key is set.
     *
     * @param string $key The session key to check.
     *
     * @return bool Returns true if the session key is set, false otherwise.
     */
    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    /**
     * Remove a session value by its key.
     *
     * @param string $key The key identifying the session value to remove.
     *
     * @return void
     */
    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }
}
