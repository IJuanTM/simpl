<?php

namespace app\Pages;

use app\Controllers\{AppController, FormController, LogController, PageController, UserController};
use app\Database\Database;

/**
 * The LoginModel class is the model for the login page.
 * It checks if the all inputs are entered and if the email is in use.
 * If the email is in use, it will call the login method from the UserController.
 */
class LoginPage
{
    public function __construct()
    {
        // Check if the login form is submitted
        if (isset($_POST['submit'])) {

            // Check if all the required fields are entered
            if (empty($_POST['email'])) {
                FormController::alert('Please enter your email!', 'warning', 'login');
                return;
            }
            if (empty($_POST['password'])) {
                FormController::alert('Please enter your password!', 'warning', 'login');
                return;
            }

            // Check if the values entered in fields are not too long
            if (strlen($_POST['email']) > 100) {
                $_POST['email'] = '';
                FormController::alert('The input of the email field is too long!', 'warning', 'login');
                return;
            }
            if (strlen($_POST['password']) > 50) {
                $_POST['password'] = '';
                FormController::alert('The input of the password field is too long!', 'warning', 'login');
                return;
            }

            // Check if the email is valid
            if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
                $_POST['email'] = '';
                FormController::alert('The entered email is not valid!', 'warning', 'login');
                return;
            }

            // Sanitize the form data
            $_POST['email'] = AppController::sanitize($_POST['email']);

            // Check if the email exists in the database
            if (!UserController::checkEmail($_POST['email'])) {
                $_POST['email'] = '';
                FormController::alert('An account with this email does not exist! Try registering!', 'warning', 'login');
                return;
            }

            // Check if the password is correct
            if (!$this->checkPassword($_POST['email'], $_POST['password'])) {
                $_POST['password'] = '';
                FormController::alert('The entered password is incorrect!', 'warning', 'login');
                return;
            }

            // Check if the user is inactive
            if (!$this->isActive($_POST['email'])) {
                $_POST['email'] = '';
                $_POST['password'] = '';
                FormController::alert('Your account is inactive! Contant an administrator for more information!', 'error', 'login');
                return;
            }

            // Check if the user has not yet verified their account
            if (!UserController::isVerified(null, $_POST['email'])) {
                $_POST['email'] = '';
                $_POST['password'] = '';
                FormController::alert('Your account has not been verified! Check your email for the verification link!', 'error', 'login');
                return;
            }

            // Login the user
            $this->login($_POST['email']);
        }
    }

    /**
     * Method for checking if the password is correct.
     *
     * @param string $email
     * @param string $password
     *
     * @return bool
     */
    private function checkPassword(string $email, string $password): bool
    {
        $db = new Database();

        // Get the password from the database
        $db->query('SELECT password FROM users WHERE email = :email');
        $db->bind(':email', $email);

        // Return true if the password is correct
        return password_verify($password, $db->single()['password']);
    }

    /**
     * Method for checking if the user is deleted.
     *
     * @param string $email
     *
     * @return bool
     */
    private function isActive(string $email): bool
    {
        $db = new Database();

        // Check if the user is deleted
        $db->query('SELECT is_active FROM users WHERE email = :email');
        $db->bind(':email', $email);

        // Return true if the user is active
        return $db->single()['is_active'] == 1;
    }

    /**
     * This method is for logging in a user.
     * It sets the session variables and redirects the user to the profile page.
     *
     * @param string $email
     */
    private function login(string $email): void
    {
        $db = new Database();

        // Get the user from the database
        $db->query('SELECT * FROM users WHERE email = :email');
        $db->bind(':email', $email);
        $user = $db->single();

        // Get the user role from the database
        $db->query('SELECT role_id FROM user_roles WHERE user_id = :id');
        $db->bind(':id', $user['id']);
        $role = $db->single()['role_id'];

        // Check if the user role is set
        if (!$role) {
            // Log an error message
            LogController::log("No user role is set for user with id \"" . $user['id'] . "\"", 'error');

            // Unset the session user
            session_unset();

            // Redirect the user to the redirect page
            FormController::alert('Error! No user role is set for this account! Please contact an admin!', 'error', REDIRECT);
            return;
        }

        // Add the role to the user array
        $user += ['role' => $role];

        // Set the session user
        $_SESSION['user'] = $user;

        // Redirect the user to the profile page
        PageController::redirect('profile');

        // Set the success message
        AppController::alert('Login successful! Welcome!', ['success', 'global'], 4);
    }
}
