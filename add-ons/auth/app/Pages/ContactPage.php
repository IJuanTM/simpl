<?php

namespace app\Pages;

use app\Controllers\AlertController;
use app\Controllers\FormController;
use app\Controllers\MailController;
use app\Controllers\PageController;
use app\Enums\AlertType;

/**
 * Handles contact form submissions and email notifications.
 */
class ContactPage
{
    public function __construct()
    {
        // Check if the contact form is submitted
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) $this->post();
    }

    /**
     * Processes contact form submission.
     */
    private function post(): void
    {
        // Validate the form fields
        if (
            !FormController::validate('name', ['required', 'maxLength' => 100]) ||
            !FormController::validate('email', ['required', 'maxLength' => 100, 'type' => 'email']) ||
            !FormController::validate('subject', ['required', 'maxLength' => 100]) ||
            !FormController::validate('message', ['required', 'maxLength' => 1000])
        ) return;

        // Send the contact email
        $this->contactMail(
            FormController::sanitize($_POST['name']),
            FormController::sanitize($_POST['email']),
            FormController::sanitize($_POST['subject']),
            FormController::sanitize($_POST['message'])
        );
    }

    /**
     * Sends contact form email to site administrator.
     *
     * @param string $from Sender's name
     * @param string $sender Sender's email address
     * @param string $subject Email subject
     * @param string $message Email message body
     */
    private function contactMail(string $from, string $sender, string $subject, string $message): void
    {
        // Get the template from the views/parts/mails folder
        $contents = MailController::template('contact', [
            'title' => 'New Contact Form Submission',
            'from' => $from,
            'date' => date('Y-m-d'),
            'time' => date('H:i'),
            'contents' => nl2br($message)
        ]);

        // Check if template was loaded successfully
        if ($contents === false) {
            FormController::addAlert('An error occurred while sending your verification email! Please contact support.', AlertType::ERROR);
            return;
        }

        // Send the message
        $result = MailController::send($from, SITE_MAIL, $sender, $subject, $contents);

        // Redirect the user to the redirect page
        PageController::redirect(REDIRECT);

        // Show appropriate alert based on email sending result
        if ($result) AlertController::alert('Your message has been sent!', AlertType::SUCCESS, 4);
        else AlertController::alert('There was a problem sending your message. Please try again later.', AlertType::ERROR, 4);
    }
}
