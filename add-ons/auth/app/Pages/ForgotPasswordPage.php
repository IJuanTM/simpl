<?php

namespace app\Pages;

use app\Controllers\AuthController;
use app\Controllers\FormController;
use app\Controllers\MailController;
use app\Controllers\SessionController;
use app\Database\Database;
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
        if (SessionController::get('timeout') !== null && SessionController::get('timeout') > time()) {
            FormController::addAlert('Please wait a moment before trying again!', AlertType::WARNING);
            return;
        }

        // Validate the form fields
        if (!FormController::validate('email', ['required', 'maxLength' => 100, 'type' => 'email'])) return;

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
        SessionController::set('timeout', time() + 60);
    }

    /**
     * Creates and stores password reset token for user.
     *
     * @param string $email User's email address
     */
    private function sendPasswordReset(string $email): void
    {
        $db = new Database();

        // Get the user id from the database
        $db->query('SELECT id FROM users WHERE email = :email');
        $db->bind(':email', $email);
        $id = $db->single()['id'];

        // Get the reset token from the database
        $db->query('SELECT token FROM tokens WHERE user_id = :id AND type = :type');
        $db->bind(':id', $id);
        $db->bind(':type', 'reset');

        // If there is a reset token in the database remove it
        if ($db->rowCount() > 0) {
            $db->query('DELETE FROM tokens WHERE user_id = :id AND type = :type');
            $db->bind(':id', $id);
            $db->bind(':type', 'reset');
            $db->execute();
        }

        // Generate a reset token
        $token = AuthController::generateToken(16);

        // Update the token in the database for the user
        $db->query('INSERT INTO tokens (user_id, token, type) VALUES(:id, :token, :type)');
        $db->bind(':id', $id);
        $db->bind(':token', $token);
        $db->bind(':type', 'reset');
        $db->execute();

        // Send a reset email to the user
        $this->passwordResetMail($id, $email, $token);
    }

    /**
     * Sends password reset email with reset link.
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

        // Check if template was loaded successfully
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
