<?php

namespace app\Controllers;

use app\Enums\AlertType;

/**
 * Manages global alert messages displayed to users.
 */
class AlertController
{
    public function __construct()
    {
        // Clear the alert if the timeout has passed
        if (SessionController::has('alert') && SessionController::get('alert')['timeout'] < time()) SessionController::remove('alert');
    }

    /**
     * Creates a global alert to be displayed after page redirect.
     *
     * @param string $message Alert message text
     * @param AlertType $type Alert type (success, warning, error, info)
     * @param int $timeout Duration in seconds before auto-dismissal (0 = no auto-dismiss)
     */
    public static function alert(string $message, AlertType $type, int $timeout = 0): void
    {
        // Set the alert in the session
        SessionController::set('alert', [
            'message' => $message,
            'type' => $type->value,
            'timeout' => time() + $timeout
        ]);
    }
}
