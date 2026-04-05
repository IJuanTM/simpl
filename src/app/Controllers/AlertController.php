<?php

declare(strict_types=1);

namespace app\Controllers;

use app\Enums\AlertType;

/**
 * Handles the creation, management, and expiration of alert messages stored in the session.
 */
class AlertController
{
    /**
     * Initializes the class by checking and removing expired alert messages from the session storage.
     *
     * Removes the alert if it exists in the session and its timeout has expired.
     *
     * @return void
     */
    public function __construct()
    {
        // Remove expired alert from session
        if (SessionController::has('alert') && SessionController::get('alert')['timeout'] < time()) SessionController::remove('alert');
    }

    /**
     * Sets a global alert message in the session with a specified type and optional timeout.
     *
     * @param string $message The alert message to be displayed.
     * @param AlertType $type The type of the alert, indicating its severity or nature.
     * @param int $timeout Optional timeout in seconds after which the alert will expire. Defaults to 0, indicating no timeout.
     * @return void
     */
    public static function globalAlert(string $message, AlertType $type, int $timeout = 0): void
    {
        SessionController::set('alert', [
            'message' => $message,
            'type' => $type->value,
            'timeout' => time() + $timeout
        ]);
    }
}
