<?php

declare(strict_types=1);

namespace app\Controllers;

/**
 * SessionController
 *
 * Lightweight wrapper around PHP sessions. This class centralises session
 * configuration and provides convenient static helpers for reading and
 * writing session values.
 *
 * Notes:
 * - Session must be available (this constructor starts it if not already active).
 * - Methods operate directly on the $_SESSION superglobal.
 */
class SessionController
{
    public function __construct()
    {
        // Configure session lifetime according to SESSION_LIFETIME constant
        ini_set('session.gc_maxlifetime', (string)(86400 * SESSION_LIFETIME));
        session_set_cookie_params(86400 * SESSION_LIFETIME);

        // Start the session if not already started
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    }

    /**
     * Set a session value.
     *
     * @param string $key
     * @param mixed $value
     *
     * @return void
     */
    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Get a session value or null when not set.
     *
     * @param string $key
     *
     * @return mixed|null Returns the stored value or null when the key is not present.
     */
    public static function get(string $key): mixed
    {
        return self::has($key) ? $_SESSION[$key] : null;
    }

    /**
     * Check whether a session key exists.
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
     * Remove a session key.
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
