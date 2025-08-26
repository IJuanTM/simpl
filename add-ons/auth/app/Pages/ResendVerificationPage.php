<?php

namespace app\Pages;

use app\Controllers\AuthController;
use app\Controllers\FormController;
use app\Controllers\MailController;
use app\Controllers\PageController;
use app\Database\Database;
use app\Enums\AlertType;
use app\Models\Page;
use app\Models\Url;

/**
 * Handles resending verification emails to users.
 */
class ResendVerificationPage
{
    /**
     * Validates user ID and initiates verification email resend process.
     *
     * @param Page $page Page object containing URL parameters
     */
    public function __construct(Page $page)
    {
        // Get the user id from the url and sanitize it
        $id = FormController::sanitize($page->urlArr['subpages'][0] ?? '');

        // Check if the user id is not empty and is numeric
        if (empty($id) || !is_numeric($id)) {
            FormController::addAlert('Undefined user id! Please contact an administrator.', AlertType::ERROR);
            PageController::redirect(REDIRECT, 2);
            return;
        }

        // Check if the user exists in the database
        if (!AuthController::exists($id)) {
            FormController::addAlert('We could not find your account! Please contact an administrator.', AlertType::ERROR);
            PageController::redirect(REDIRECT, 2);
            return;
        }

        // Check if the user is trying to verify their account
        if (AuthController::isVerified($id)) {
            FormController::addAlert('Your account is currently not being verified!', AlertType::INFO);
            PageController::redirect(REDIRECT, 2);
            return;
        }

        // Resend the verification code
        $this->resendVerification($id);
    }

    /**
     * Generates new verification token and updates database.
     *
     * @param int $id User ID
     */
    private function resendVerification(int $id): void
    {
        $db = new Database();

        // Generate a new verification token
        $token = AuthController::generateToken(4);

        // Update the code in the database for the user
        $db->query('UPDATE tokens SET token = :token WHERE user_id = :id AND type = :type');
        $db->bind(':id', $id);
        $db->bind(':token', $token);
        $db->bind(':type', 'verification');
        $db->execute();

        // Get the email of the user
        $db->query('SELECT email FROM users WHERE id = :id');
        $db->bind(':id', $id);

        // Send a verification email to the user
        $this->resendVerificationMail($id, $db->single()['email'], $token);
    }

    /**
     * Sends verification email with new token and redirects user.
     *
     * @param int $id User ID
     * @param string $to User's email address
     * @param string $code Verification code
     */
    private function resendVerificationMail(int $id, string $to, string $code): void
    {
        // Get the template from the views/parts/mails folder
        $contents = MailController::template('verification', [
            'title' => 'Account Verification - ' . APP_NAME,
            'link' => Url::to("verify-account/$id/$code"),
            'code' => $code
        ]);

        // Check if template was loaded successfully
        if ($contents === false) {
            FormController::addAlert('An error occurred while sending your verification email! Please contact support.', AlertType::ERROR);
            return;
        }

        // Send the message
        $result = MailController::send(APP_NAME, $to, NO_REPLY_MAIL, 'Verify account', $contents);

        // Show appropriate alert based on email sending result
        if ($result) FormController::addAlert('Success! A new verification email has been sent!', AlertType::SUCCESS);
        else FormController::addAlert('An error occurred while sending your verification email! Please contact support.', AlertType::ERROR);
    }
}
