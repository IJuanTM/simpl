<?php

declare(strict_types=1);

namespace app\Controllers;

use app\Enums\AlertType;

/**
 * AlertController
 *
 * Responsible for creating and maintaining global alert messages stored in the
 * session. Alerts are intended for short-lived user notifications (flash
 * messages) that survive redirects.
 *
 * Notes:
 * - Uses `SessionController` (session must be started by the caller/framework).
 * - The stored alert shape is: ['message' => string, 'type' => string, 'timeout' => int]
 */
class AlertController
{
    /**
     * Clean up expired alert stored in session.
     *
     * This constructor performs a lightweight maintenance task: if a previously
     * stored alert has expired it will be removed from the session. No other
     * side effects are performed.
     */
    public function __construct()
    {
        // Remove expired alert from session
        if (SessionController::has('alert') && SessionController::get('alert')['timeout'] < time()) SessionController::remove('alert');
    }

    /**
     * Store a global alert in session to display after redirects.
     *
     * @param string $message Alert message text
     * @param AlertType $type Alert severity/type (uses enum value for CSS class)
     * @param int $timeout Duration in seconds the alert should remain visible (0 = persistent)
     *
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
