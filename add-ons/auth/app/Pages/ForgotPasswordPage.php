<?php

namespace app\Pages;

use app\Controllers\AuthController;
use app\Controllers\FormController;
use app\Controllers\MailController;
use app\Controllers\SessionController;
use app\Database\DB;
use app\Enums\AlertType;
use app\Models\Url;

/**
 * Handles password reset request functionality.
 */
class ForgotPasswordPage
{
    public function __construct()
    {
        // Check if the forgot password form is submitted
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) $this->post();
    }

    /**
     * Processes password reset request form submission.
     */
    private function post(): void
    {
        // Check if the timeout is not exceeded
        if (SessionController::get('resend-timeout') !== null && SessionController::get('resend-timeout') > time()) {
            FormController::addAlert('Please wait a moment before trying again!', AlertType::WARNING);
            return;
        }

        // Validate the form fields
        if (
            !FormController::validate('email', ['required', 'maxLength' => 100, 'type' => 'email'])
        ) return;

        // Sanitize the form data
        $_POST['email'] = FormController::sanitize($_POST['email']);

        // Check if the email exists in the database
        if (!AuthController::checkEmail($_POST['email'])) {
            $_POST['email'] = '';

            FormController::addAlert('An account with this email does not exist!', AlertType::WARNING);
            return;
        }

        // Send the reset link
        $this->sendPasswordReset($_POST['email']);

        // Set the timeout to 1 minute
        SessionController::set('resend-timeout', time() + 60);
    }

    /**
     * Creates and stores password reset token for user.
     *
     * @param string $email User's email address
     */
    private function sendPasswordReset(string $email): void
    {
        // Get the user id from the database
        $id = DB::single(
            'id',
            'users',
            compact('email')
        )['id'];

        // If there is a reset token in the database, remove it
        if (DB::exists(
            'tokens',
            ['user_id' => $id, 'type' => 'reset'])
        ) DB::delete(
            'tokens',
            ['user_id' => $id, 'type' => 'reset']
        );

        // Generate a reset token
        $token = AuthController::generateToken();

        // Update the token in the database for the user
        DB::insert(
            'tokens',
            ['user_id' => $id, 'token' => $token, 'type' => 'reset']
        );

        // Send a reset email to the user
        $this->passwordResetMail($id, $email, $token);
    }

    /**
     * Sends password reset email with a reset link.
     *
     * @param int $id User ID
     * @param string $to User's email address
     * @param string $token Reset token
     */
    private function passwordResetMail(int $id, string $to, string $token): void
    {
        // Get the template from the views/parts/mails folder
        $contents = MailController::template('reset-password', [
            'title' => 'Password Reset Request - ' . APP_NAME,
            'link' => Url::to("reset-password/$id/$token")
        ]);

        // Check if the template was loaded successfully
        if ($contents === false) {
            FormController::addAlert('An error occurred while sending your verification email! Please contact support.', AlertType::ERROR);
            return;
        }

        // Send the message and handle the result
        $result = MailController::send(APP_NAME, $to, NO_REPLY_MAIL, 'Reset password', $contents);

        // Show appropriate alert based on email sending result
        if ($result) FormController::addAlert('Success! A reset link has been sent to your email!', AlertType::SUCCESS);
        else FormController::addAlert('An error occurred while sending the reset link. Please contact support.', AlertType::ERROR);
    }
}
