<?php

declare(strict_types=1);

namespace app\Pages;

use app\Controllers\AlertController;
use app\Controllers\FormController;
use app\Controllers\MailController;
use app\Controllers\PageController;
use app\Controllers\RequestController;
use app\Enums\AlertType;
use app\Utils\RateLimiter;

/**
 * ContactPage
 *
 * Processes submissions from the site's contact form and forwards messages to
 * the configured site mail address.
 */
class ContactPage
{
    public int $sendCooldown = 0;

    public function __construct()
    {
        $this->sendCooldown = RateLimiter::retryAfterMs('contact');

        // Process contact form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) $this->post();
    }

    /**
     * Processes contact form submission.
     *
     * @return void
     */
    private function post(): void
    {
        // Validate form fields
        if (
            !FormController::validate('name', ['required', 'maxLength' => MAX_NAME_LENGTH]) ||
            !FormController::validate('email', ['required', 'maxLength' => MAX_EMAIL_LENGTH, 'type' => 'email']) ||
            !FormController::validate('subject', ['required', 'maxLength' => MAX_CONTACT_SUBJECT_LENGTH]) ||
            !FormController::validate('message', ['required', 'maxLength' => MAX_CONTACT_MESSAGE_LENGTH])
        ) return;

        // Rate limit after validation to avoid consuming slots on invalid input
        if (!RateLimiter::attempt('contact', 1, CONTACT_RESEND_TIMEOUT)) {
            FormController::addAlert('Too many submissions. Please wait a moment before trying again.', AlertType::WARNING);
            return;
        }

        // Send contact email
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
        // Get email template
        $contents = MailController::template('contact', [
            'title' => 'New Contact Form Submission',
            'from' => $from,
            'date' => date('Y-m-d'),
            'time' => date('H:i'),
            'contents' => nl2br($message)
        ]);

        // Check if template loaded successfully
        if ($contents === false) {
            FormController::addAlert('An error occurred while sending your verification email! Please contact support.', AlertType::ERROR);
            return;
        }

        // Send email
        $result = MailController::send($from, SITE_MAIL, $sender, $subject, $contents);

        // Redirect to home
        PageController::redirect(REDIRECT);

        // Show appropriate alert
        if ($result) AlertController::globalAlert('Your message has been sent!', AlertType::SUCCESS, 4);
        else AlertController::globalAlert('There was a problem sending your message. Please try again later.', AlertType::ERROR, 4);
    }
}
