<?php

declare(strict_types=1);

namespace app\Controllers;

use app\Enums\AlertType;

/**
 * Session-stored toast notifications: queue one via add(), and read and consume the pending ones via pull() on the page that shows them.
 */
class ToastController
{
    /**
     * Queues a toast in the session, so it survives a redirect and shows on the next rendered page.
     *
     * @param string    $message
     * @param AlertType $type
     * @param int       $timeout Seconds the toast stays on screen before it closes itself. 0 (default) keeps it open until the user closes it.
     *
     * @return void
     */
    public static function add(string $message, AlertType $type, int $timeout = 0): void
    {
        SessionController::set('toasts', [
            ...SessionController::get('toasts') ?? [],
            ['message' => $message, 'type' => $type->value, 'timeout' => max(0, $timeout)]
        ]);
    }

    /**
     * Returns and consumes the pending toasts so each one shows once.
     * Skipped on a 302 body, whose output the browser discards; those toasts belong to the redirect target.
     *
     * @return list<array{message: string, type: string, timeout: int}>
     */
    public static function pull(): array
    {
        $toasts = SessionController::get('toasts') ?? [];
        if (http_response_code() !== 302) SessionController::remove('toasts');
        return $toasts;
    }
}
