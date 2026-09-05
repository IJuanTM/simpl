<?php

declare(strict_types=1);

namespace app\Controllers;

use app\Enums\AlertType;

/**
 * Session-stored alert messages: queue one via globalAlert(), read the pending one via pull(), and expire timed ones on construction.
 */
class AlertController
{
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
     * @param string    $message
     * @param AlertType $type
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

    /**
     * Returns the pending alert and consumes a non-expiring (timeout 0) one so it renders once.
     * Skipped on a 302 body, whose output the browser discards - that alert belongs to the redirect target.
     * Timed alerts are left in the session for the constructor to expire on their own schedule.
     *
     * @return array{message: string, type: string, timeout: int}|null
     */
    public static function pull(): ?array
    {
        $alert = SessionController::get('alert');
        if ($alert !== null && $alert['timeout'] === 0 && http_response_code() !== 302) SessionController::remove('alert');
        return $alert;
    }
}
