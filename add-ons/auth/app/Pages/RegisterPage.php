<?php

namespace app\Pages;

use app\Controllers\AlertController;
use app\Controllers\AuthController;
use app\Controllers\FormController;
use app\Controllers\MailController;
use app\Controllers\PageController;
use app\Database\Database;
use app\Enums\AlertType;
use app\Models\Url;

/**
 * Handles user registration functionality.
 */
class RegisterPage
{
    public function __construct()
    {
        // Check if the register form is submitted
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) $this->post();
    }

    /**
     * Processes registration form submission.
     */
    private function post(): void
    {
        // Validate the form fields
        if (
            !FormController::validate('email', ['required', 'maxLength' => 100, 'type' => 'email']) ||
            !FormController::validate('password', ['required', 'maxLength' => 50]) ||
            !FormController::validate('password-check', ['required', 'maxLength' => 50])
        ) return;

        // Sanitize the email
        $_POST['email'] = FormController::sanitize($_POST['email']);

        // Check if the email is already in use by another user
        if (AuthController::checkEmail($_POST['email'])) {
            $_POST['email'] = '';

            FormController::addAlert('An account with this email already exists! Try logging in!', AlertType::WARNING);
            return;
        }

        // Check if the password contains at least 8 characters, 1 uppercase letter, 1 lowercase letter and 1 number
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $_POST['password'])) {
            $_POST['password'] = '';
            $_POST['password-check'] = '';

            FormController::addAlert('Your password must contain at least 8 characters, 1 uppercase letter, 1 lowercase letter and 1 number!', AlertType::WARNING);
            return;
        }

        // Check if the password and password check are the same
        if ($_POST['password'] !== $_POST['password-check']) {
            FormController::addAlert('The entered passwords do not match!', AlertType::WARNING);
            return;
        }

        // Register the user
        $this->register($_POST['email'], $_POST['password']);
    }

    /**
     * Creates a new user account and sends verification email.
     *
     * @param string $email User's email address
     * @param string $password User's password (will be hashed)
     */
    private function register(string $email, string $password): void
    {
        $db = new Database();

        // Push the new user to the database
        $db->query('INSERT INTO users (email, password) VALUES(:email, :password_hash)');
        $db->bind(':email', $email);
        $db->bind(':password_hash', password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]));
        $db->execute();

        // Get the id of the new user
        $db->query('SELECT id FROM users WHERE email = :email');
        $db->bind(':email', $email);
        $id = $db->single()['id'];

        // Set the user role
        $db->query('INSERT INTO user_roles (user_id) VALUES(:id)');
        $db->bind(':id', $id);
        $db->execute();

        // Generate a verification token
        $token = AuthController::generateToken(4);

        // Set the verification token in the database
        $db->query('INSERT INTO tokens (user_id, token, type) VALUES(:id, :token, :type)');
        $db->bind(':id', $id);
        $db->bind(':token', $token);
        $db->bind(':type', 'verification');
        $db->execute();

        // Send a verification email to the user
        $this->verificationMail($id, $email, $token);
    }

    /**
     * Sends verification email and redirects to verification page.
     *
     * @param int $id User ID
     * @param string $to User's email address
     * @param string $code Verification code
     */
    private function verificationMail(int $id, string $to, string $code): void
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

        // Send the email and handle the result
        $result = MailController::send(APP_NAME, $to, NO_REPLY_MAIL, 'Verify account', $contents);

        // Redirect the user to the verification page
        PageController::redirect("verify-account/$id");

        // Show appropriate alert based on email sending result
        if ($result) AlertController::alert('Success! Your account has been created! A verification email has been sent!', AlertType::SUCCESS, 4);
        else AlertController::alert('Your account has been created! However, there was an issue sending the verification email. Please contact support.', AlertType::ERROR, 8);
    }
}
