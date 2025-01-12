<?php

namespace app\Controllers;

/**
 * The AlertController class is the controller for showing global alerts.
 */
class AlertController
{
    public function __construct()
    {
        // Clear the alert if the timeout has passed
        if (SessionController::has('alert') && SessionController::get('alert')['timeout'] < time()) SessionController::remove('alert');
    }

    /**
     * Method for showing a global alert at the bottom of the page.
     *
     * @param string $message
     * @param array $types
     * @param int $timeout
     *
     * @return void
     */
    public static function alert(string $message, array $types, int $timeout = 0): void
    {
        // Set the alert in the session
        SessionController::set('alert', [
            'message' => $message,
            'types' => $types,
            'timeout' => time() + $timeout
        ]);
    }
}
