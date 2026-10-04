<?php

declare(strict_types=1);

namespace app\Pages;

use app\Controllers\FormController;
use app\Controllers\MailController;
use app\Controllers\RequestController;
use app\Enums\AlertType;
use app\Pages\Traits\RateLimitedForm;
use app\Utils\RateLimiter;

/**
 * Processes submissions from the contact modal and forwards messages to the configured site mail address.
 */
class ContactPage
{
    use RateLimitedForm;

    /**
     * Only ever reached via /api/contact, which contact.ts calls in the background.
     * Answers JSON so the modal can show the result and keep what was typed.
     *
     * @return void
     */
    public function api(): void
    {
        if (!$this->initRateLimitedForm('contact')) {
            self::respond(400, ['alerts' => '']);
            return;
        }

        if (
            !FormController::validate('name', ['required', 'singleLine', 'maxLength' => MAX_NAME_LENGTH]) ||
            !FormController::validate('email', ['required', 'maxLength' => MAX_EMAIL_LENGTH, 'type' => 'email']) ||
            !FormController::validate('subject', ['required', 'singleLine', 'maxLength' => MAX_CONTACT_SUBJECT_LENGTH]) ||
            !FormController::validate('message', ['required', 'maxLength' => MAX_CONTACT_MESSAGE_LENGTH])
        ) {
            $this->fail(422);
            return;
        }

        // Rate limit after validation to avoid consuming slots on invalid input
        if (!$this->attemptRateLimit(RESEND_TIMEOUTS['contact'])) {
            $this->fail(429);
            return;
        }

        $this->contactMail(
            RequestController::rawPost('name'),
            RequestController::rawPost('email'),
            RequestController::rawPost('subject'),
            RequestController::rawPost('message')
        );
    }

    /**
     * Emits $data as a JSON response body with the given status.
     *
     * @param int                  $status
     * @param array<string, mixed> $data
     *
     * @return void
     */
    private static function respond(int $status, array $data): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_THROW_ON_ERROR);
    }

    /**
     * Answers a failed submission with the queued form alerts, shown inside the modal.
     *
     * @param int $status HTTP status for the response
     *
     * @return void
     */
    private function fail(int $status): void
    {
        self::respond($status, ['alerts' => FormController::formAlerts() ?? '', 'cooldown' => $this->remainingCooldown()]);
    }

    /**
     * Milliseconds until this IP may send another message.
     *
     * @return int
     */
    private function remainingCooldown(): int
    {
        return $this->cooldown ?: RateLimiter::retryAfterMs($this->rlKey);
    }

    /**
     * Sends contact form email to site administrator.
     *
     * @param string $from    Sender's name
     * @param string $sender  Sender's email address
     * @param string $subject Email subject
     * @param string $message Email message body
     *
     * @return void
     */
    private function contactMail(string $from, string $sender, string $subject, string $message): void
    {
        $contents = MailController::template('contact', [
            'title' => 'New Contact Form Submission',
            'from' => $from,
            'email' => $sender,
            'date' => date('Y-m-d'),
            'time' => date('H:i'),
            'contents' => $message
        ]);

        if ($contents === false || !MailController::send($from, MAIL_CONFIG['site_address'], MAIL_CONFIG['no_reply_address'], $subject, $contents, $sender)) {
            FormController::addAlert('There was a problem sending your message. Please try again later.', AlertType::ERROR);
            $this->fail(500);
            return;
        }

        self::respond(200, ['message' => 'Your message has been sent!', 'cooldown' => $this->remainingCooldown()]);
    }
}
