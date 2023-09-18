<?php

namespace app\Pages;

use app\Controllers\{AppController, FormController, MailController, PageController, UserController};
use app\Database\Database;

/**
 * The ProfilePage class is the controller for the profile page.
 * It checks if the user is logged in and redirects the user to the 403 page if not.
 */
class ProfilePage
{
    public function __construct()
    {
        // Check if the user is logged in
        if (!isset($_SESSION['user']['id'])) {
            PageController::redirect('error/403');
            exit;
        }

        // Check if the profile form is submitted
        if (isset($_POST['submit'])) {
            // Check if the required fields are not empty
            if (empty($_POST['email'])) {
                FormController::alert('Please enter your email!', 'warning', 'profile');
                return;
            }

            // Check if the values entered in the fields are not too long
            if (strlen($_POST['name']) > 100) {
                FormController::alert('The input of the name field is too long!', 'warning', 'profile');
                return;
            }
            if (strlen($_POST['email']) > 100) {
                FormController::alert('The input of the email field is too long!', 'warning', 'profile');
                return;
            }

            // Check if the email is valid
            if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
                $_POST['email'] = $_SESSION['user']['email'];
                FormController::alert('The entered email is not valid!', 'warning', 'profile');
                return;
            }

            // Sanitize the email
            $_POST['email'] = AppController::sanitize($_POST['email']);

            // Check if the email is changed and if it is already in use by another user
            if ($_SESSION['user']['email'] !== $_POST['email'] && UserController::checkEmail($_POST['email'])) {
                $_POST['email'] = $_SESSION['user']['email'];
                FormController::alert('An account with this email already exists!', 'warning', 'profile');
                return;
            }

            // Update the user
            self::update($_SESSION['user']['id'], AppController::sanitize($_POST['name']), $_POST['email']);
        }
    }

    /**
     * This method is for updating an user's profile.
     * A user can update their name and email.
     *
     * @param int $id
     * @param string $name
     * @param string $email
     *
     * @return void
     */
    public static function update(int $id, string $name, string $email): void
    {
        $db = new Database();

        // Check if the name has changed
        if ($_SESSION['user']['name'] !== $name) {
            // Update the name in the database
            $db->query('UPDATE users SET name = :name WHERE id = :id');
            $db->bind(':name', $name);
            $db->bind(':id', $id);
            $db->execute();
        }

        // Check if the email has changed
        if ($_SESSION['user']['email'] !== $email) {
            // Check if the email is already in use
            if (UserController::checkEmail($email)) {
                FormController::alert('An account with this email already exists!', 'warning', "users/edit/$id");
                return;
            }

            // Update the email in the database
            $db->query('UPDATE users SET email = :email WHERE id = :id');
            $db->bind(':email', $email);
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

            // Redirect to the logout page
            PageController::redirect('logout');

            // Show the success message
            AppController::alert('Success! Your profile has been updated! Please verify your new email address!', ['success', 'global'], 4);
            return;
        }

        // Get the updated user from the database
        $db->query('SELECT * FROM users WHERE id = :id');
        $db->bind(':id', $id);
        $user = $db->single();

        // Add the role to the user array
        $user += ['role' => $_SESSION['user']['role']];

        // Update the user session
        $_SESSION['user'] = $user;

        // Redirect to the profile page
        PageController::redirect('profile');

        // Show the success message
        AppController::alert('Success! Your profile has been updated!', ['success', 'global'], 4);
    }

}
