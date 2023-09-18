<?php

namespace app\Pages;

use app\Controllers\{AppController, FormController, MailController, UserController};
use app\Database\Database;

/**
 * The RegisterPage class is the controller for the register page.
 * It checks if all inputs are entered and if the email is in use.
 * If the email is not in use, it will call the register method from the UserController.
 */
class RegisterPage
{
    public function __construct()
    {
        // Check if the register form is submitted
        if (isset($_POST['submit'])) {
            // Check if all the required fields are entered
            if (empty($_POST['email'])) {
                FormController::alert('Please enter your email!', 'warning', 'register');
                return;
            }
            if (empty($_POST['password'])) {
                FormController::alert('Please enter your password!', 'warning', 'register');
                return;
            }
            if (empty($_POST['password-check'])) {
                FormController::alert('Please repeat your password!', 'warning', 'register');
                return;
            }

            // Check if the values entered in fields are not too long
            if (strlen($_POST['email']) > 100) {
                $_POST['email'] = '';
                FormController::alert('The input of the email field is too long!', 'warning', 'register');
                return;
            }
            if (strlen($_POST['password']) > 50) {
                $_POST['password'] = '';
                FormController::alert('The input of the password field is too long!', 'warning', 'register');
                return;
            }
            if (strlen($_POST['password-check']) > 50) {
                $_POST['password-check'] = '';
                FormController::alert('The input of the password check field is too long!', 'warning', 'register');
                return;
            }

            // Check if the email is valid
            if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
                $_POST['email'] = '';
                FormController::alert('The entered email is not valid!', 'warning', 'register');
                return;
            }

            // Sanitize the email
            $_POST['email'] = AppController::sanitize($_POST['email']);

            // Check if the email is already in use by another user
            if (UserController::checkEmail($_POST['email'])) {
                $_POST['email'] = '';
                FormController::alert('An account with this email already exists! Try logging in!', 'warning', 'register');
                return;
            }

            // Check if the password contains at least 8 characters, 1 uppercase letter, 1 lowercase letter and 1 number
            if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $_POST['password'])) {
                $_POST['password'] = '';
                $_POST['password-check'] = '';
                FormController::alert('Your password must contain at least 8 characters, 1 uppercase letter, 1 lowercase letter and 1 number!', 'warning', 'register');
                return;
            }

            // Check if the password and password check are the same
            if ($_POST['password'] != $_POST['password-check']) {
                FormController::alert('The entered passwords do not match!', 'warning', 'register');
                return;
            }

            // Register the user
            $this->register($_POST['email'], $_POST['password']);
        }
    }

    /**
     * This method is for registering a new user.
     * It generates a verification code, pushes the new user to the database, sets the user role and sends a verification email.
     *
     * @param string $email
     * @param string $password
     *
     * @return void
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
        $token = UserController::generateToken(4);

        // Set the verification token in the database
        $db->query('INSERT INTO tokens (user_id, token, type) VALUES(:id, :token, :type)');
        $db->bind(':id', $id);
        $db->bind(':token', $token);
        $db->bind(':type', 'verification');
        $db->execute();

        // Send a verification email to the user
        MailController::verification($id, $email, $token);

        // Redirect the user to the verification page
        FormController::alert('Success! Your account has been created!', 'success', "verify-account/$id", 2);
    }
}
