<?php

namespace app\Pages;

use app\Controllers\AlertController;
use app\Controllers\AuthController;
use app\Controllers\FormController;
use app\Controllers\PageController;
use app\Database\Database;
use app\Enums\AlertType;

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

        // Check if the email is already in use
        if (AuthController::checkEmail($_POST['email'])) {
            $_POST['email'] = '';

            FormController::addAlert('An account with this email already exists! Try logging in!', AlertType::WARNING);
            return;
        }

        // Validate the password against the password policy
        if (!AuthController::validatePassword($_POST['password'])) {
            $_POST['password'] = '';
            $_POST['password-check'] = '';
            return;
        }

        // Check if passwords match
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

        if (EMAIL_VERIFICATION_REQUIRED) {
            // Generate a verification token
            $token = AuthController::generateToken(4);

            // Set the verification token in the database
            $db->query('INSERT INTO tokens (user_id, token, type) VALUES(:id, :token, :type)');
            $db->bind(':id', $id);
            $db->bind(':token', $token);
            $db->bind(':type', 'verification');
            $db->execute();

            // Send a verification email to the user
            AuthController::sendVerificationMail($id, $email, $token);
        } else {
            // If no email verification is required, redirect the user to the login page with a success message
            PageController::redirect("login");
            AlertController::alert('Success! Your account has been created!', AlertType::SUCCESS, 4);
        }
    }
}
