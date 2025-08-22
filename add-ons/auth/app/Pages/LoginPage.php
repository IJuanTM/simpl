<?php

namespace app\Pages;

use app\Controllers\AlertController;
use app\Controllers\AuthController;
use app\Controllers\FormController;
use app\Controllers\PageController;
use app\Database\Database;
use app\Enums\AlertType;

/**
 * Handles user authentication and login process.
 */
class LoginPage
{
    public function __construct()
    {
        // Check if the login form is submitted
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) $this->post();
    }

    private function post(): void
    {
        $valid = true;

        // Validate the form fields
        if (!FormController::validate('email', ['required', 'maxLength' => 100, 'type' => 'email'])) $valid = false;
        if (!FormController::validate('password', ['required', 'maxLength' => 50])) $valid = false;

        if (!$valid) return;

        // Sanitize the form data
        $_POST['email'] = FormController::sanitize($_POST['email']);

        // Check if the email exists in the database
        if (!AuthController::checkEmail($_POST['email'])) {
            $_POST['email'] = '';

            FormController::addAlert('An account with this email does not exist! Try registering!', AlertType::WARNING);
            return;
        }

        // Check if the password is correct
        if (!AuthController::checkPassword($_POST['email'], $_POST['password'])) {
            $_POST['password'] = '';

            FormController::addAlert('The entered password is incorrect!', AlertType::WARNING);
            return;
        }

        // Check if the user is inactive
        if (!AuthController::isActive($_POST['email'])) {
            $_POST['email'] = '';
            $_POST['password'] = '';

            FormController::addAlert('Your account is inactive! Contant an administrator for more information!', AlertType::ERROR);
            return;
        }

        // Check if the user has not yet verified their account
        if (!AuthController::isVerified(null, $_POST['email'])) {
            $_POST['email'] = '';
            $_POST['password'] = '';

            FormController::addAlert('Your account has not been verified! Check your email for the verification link!', AlertType::ERROR);
            return;
        }

        // Login the user
        $this->login($_POST['email']);
    }

    /**
     * Authenticates user and creates session.
     *
     * @param string $email User's email address
     */
    private function login(string $email): void
    {
        $db = new Database();

        // Get the user from the database
        $db->query('SELECT * FROM users WHERE email = :email');
        $db->bind(':email', $email);
        $user = $db->single();

        // Remove the password from the user array
        unset($user['password']);

        // Set the user in the session
        if (!AuthController::setUserSession($user)) {
            FormController::addAlert('An error occurred while trying to log you in! Please try again!', AlertType::ERROR);
            return;
        }

        // Check if the user has the remember me checkbox checked
        if (isset($_POST['remember'])) {
            // Generate a remember token
            $token = AuthController::generateToken(16);

            // Timestamp for the cookie (30 days)
            $timestamp = time() + (86400 * 30);

            // Set the cookie
            setcookie('remember', $token, $timestamp, '/');

            // Set the token in the database
            $this->setRememberToken($user['id'], $token, $timestamp);
        }

        // Redirect the user to the profile page with a success message
        PageController::redirect('profile');
        AlertController::alert('Login successful! Welcome!', AlertType::SUCCESS, 4);
    }

    /**
     * Stores remember-me token in database.
     *
     * @param int $id User ID
     * @param string $token Remember token
     * @param int $timestamp Expiration timestamp
     */
    private function setRememberToken(int $id, string $token, int $timestamp): void
    {
        $db = new Database();

        // Delete the old token(s) from the database
        $db->query('DELETE FROM tokens WHERE user_id = :id AND type = :type');
        $db->bind(':id', $id);
        $db->bind(':type', 'remember');
        $db->execute();

        // Set the token in the database
        $db->query('INSERT INTO tokens (user_id, token, type, expires) VALUES(:id, :token, :type, :expires)');
        $db->bind(':id', $id);
        $db->bind(':token', $token);
        $db->bind(':type', 'remember');
        $db->bind(':expires', date('Y-m-d H:i:s', $timestamp));
        $db->execute();
    }
}
