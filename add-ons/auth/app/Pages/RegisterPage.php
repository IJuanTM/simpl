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

        // Check if the email is already in use
        if (AuthController::checkEmail($_POST['email'])) {
            $_POST['email'] = '';

            FormController::addAlert('An account with this email already exists! Try logging in!', AlertType::WARNING);
            return;
        }

        // Validate the entered password
        if (!$this->validatePassword($_POST['password'])) {
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
     * Validates the password against defined security rules, see the auth config to change them.
     *
     * @param string $password The password to validate
     *
     * @return bool Whether the password is valid
     */
    private function validatePassword(string $password): bool
    {
        $rules = array_filter([
            REQUIRE_LOWERCASE ? ['(?=.*[a-z])', '1 lowercase letter'] : null,
            REQUIRE_UPPERCASE ? ['(?=.*[A-Z])', '1 uppercase letter'] : null,
            REQUIRE_NUMBER ? ['(?=.*\d)', '1 number'] : null,
            REQUIRE_SPECIAL_CHARACTER ? ['(?=.*[^a-zA-Z\d])', '1 special character'] : null,
        ]);

        // Build the regex pattern
        $pattern = '/^' . implode('', array_column($rules, 0)) . '.{' . MIN_PASSWORD_LENGTH . ',}$/';

        // Create an error message based on the rules
        $messages = array_column($rules, 1);
        array_unshift($messages, "at least " . MIN_PASSWORD_LENGTH . " characters");
        $errorMessage = "Your password must contain " . (count($messages) > 1 ? implode(', ', array_slice($messages, 0, -1)) . ' and ' : '') . end($messages) . "!";

        // Validate the password against the pattern
        if (!preg_match($pattern, $password)) {
            FormController::addAlert($errorMessage, AlertType::WARNING);
            return false;
        }

        return true;
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
            $this->verificationMail($id, $email, $token);
        } else {
            // If no email verification is required, redirect the user to the login page with a success message
            PageController::redirect("login");
            AlertController::alert('Success! Your account has been created!', AlertType::SUCCESS, 4);
        }
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
