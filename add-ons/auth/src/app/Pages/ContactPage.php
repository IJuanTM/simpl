<?php

declare(strict_types=1);

namespace app\Pages;

use app\Controllers\FormController;
use app\Controllers\MailController;
use app\Controllers\PageController;
use app\Controllers\RequestController;
use app\Enums\AlertType;
use app\Pages\Traits\RateLimitedForm;

/**
 * ContactPage
 *
 * Processes submissions from the site's contact form and forwards messages to
 * the configured site mail address.
 */
class ContactPage
{
    use RateLimitedForm;

    public function __construct()
    {
        if ($this->initRateLimitedForm('contact')) $this->post();
    }

    /**
     * Processes contact form submission.
     *
     * @return void
     */
    private function post(): void
    {
        if (
            !FormController::validate('name', ['required', 'maxLength' => MAX_NAME_LENGTH]) ||
            !FormController::validate('email', ['required', 'maxLength' => MAX_EMAIL_LENGTH, 'type' => 'email']) ||
            !FormController::validate('subject', ['required', 'maxLength' => MAX_CONTACT_SUBJECT_LENGTH]) ||
            !FormController::validate('message', ['required', 'maxLength' => MAX_CONTACT_MESSAGE_LENGTH])
        ) return;

        // Rate limit after validation to avoid consuming slots on invalid input
        if (!$this->attemptRateLimit(RESEND_TIMEOUTS['contact'])) return;

        $this->contactMail(
            RequestController::post('name'),
            RequestController::post('email'),
            RequestController::post('subject'),
            RequestController::post('message')
        );
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

        if ($contents === false) {
            FormController::addAlert('An error occurred while sending your message! Please contact support.', AlertType::ERROR);
            return;
        }

        $result = MailController::send($from, MAIL_CONFIG['site_address'], MAIL_CONFIG['no_reply_address'], $subject, $contents, $sender);

        if ($result) PageController::redirectWithAlert(REDIRECT, 'Your message has been sent!', AlertType::SUCCESS, 4);
        else PageController::redirectWithAlert(REDIRECT, 'There was a problem sending your message. Please try again later.', AlertType::ERROR, 4);
    }
}
