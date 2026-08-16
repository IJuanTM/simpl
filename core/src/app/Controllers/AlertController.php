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
        // timeout=0 means never expire.
        if (SessionController::has('alert')) {
            $alert = SessionController::get('alert');
            if ($alert['timeout'] !== 0 && $alert['timeout'] < time()) SessionController::remove('alert');
        }
    }

    /**
     * Sets a global alert message in the session with a specified type and optional timeout.
     *
     * @param string    $message The alert message to be displayed.
     * @param AlertType $type    The type of the alert, indicating its severity or nature.
     * @param int       $timeout Seconds until the alert expires. 0 (default) means the alert persists until the next page load.
     *
     * @return void
     */
    public static function globalAlert(string $message, AlertType $type, int $timeout = 0): void
    {
        SessionController::set('alert', [
            'message' => AppController::sanitize($message),
            'type' => $type->value,
            'timeout' => $timeout > 0 ? time() + $timeout : 0
        ]);
    }
}
