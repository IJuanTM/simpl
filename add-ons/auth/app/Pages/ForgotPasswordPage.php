<?php

namespace app\Pages;

use app\Controllers\{AppController, FormController, MailController, UserController};
use app\Database\Database;

class ForgotPasswordPage
{
    public function __construct()
    {
        // Check if the forgot password form is submitted
        if (isset($_POST['submit'])) {
            // Check if the timeout is not exceeded
            if (isset($_SESSION['timeout']) && $_SESSION['timeout'] > time()) {
                FormController::alert('Please wait a moment before trying again!', 'warning', 'forgot-password');
                return;
            }

            // Check if all the required fields are entered
            if (empty($_POST['email'])) {
                FormController::alert('Please enter your email!', 'warning', 'forgot-password');
                return;
            }

            // Check if the values entered in fields are not too long
            if (strlen($_POST['email']) > 100) {
                $_POST['email'] = '';
                FormController::alert('The input of the email field is too long!', 'warning', 'forgot-password');
                return;
            }

            // Check if the email is valid
            if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
                $_POST['email'] = '';
                FormController::alert('The entered email is not valid!', 'warning', 'forgot-password');
                return;
            }

            // Sanitize the form data
            $_POST['email'] = AppController::sanitize($_POST['email']);

            // Check if the email exists in the database
            if (!UserController::checkEmail($_POST['email'])) {
                $_POST['email'] = '';
                FormController::alert('An account with this email does not exist!', 'warning', 'forgot-password');
                return;
            }

            // Send the reset link
            $this->sendPasswordReset($_POST['email']);

            // Set the timeout to 1 minute
            $_SESSION['timeout'] = time() + 60;
        }
    }

    /**
     * This method is sending a reset link to the user. The link contains the user id and a reset token.
     *
     * @param string $email
     *
     * @return void
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
        $token = UserController::generateToken(16);

        // Update the token in the database for the user
        $db->query('INSERT INTO tokens (user_id, token, type) VALUES(:id, :token, :type)');
        $db->bind(':id', $id);
        $db->bind(':token', $token);
        $db->bind(':type', 'reset');
        $db->execute();

        // Send a reset email to the user
        MailController::reset($id, $email, $token);

        // Redirect the user to the reset page
        FormController::alert('Success! A reset link has been sent to your email!', 'success', 'forgot-password');
    }
}
