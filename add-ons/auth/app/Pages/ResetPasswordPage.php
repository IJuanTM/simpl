<?php

namespace app\Pages;

use app\Controllers\{FormController, UserController};
use app\Database\Database;
use app\Models\PageModel;

class ResetPasswordPage
{
    public function __construct(PageModel $page)
    {
        // Check if a user id and a token are given in the url, if the user id is a number and if the given user id and token are in the database
        if (!isset($page->getUrl()['subpages'][0])
            || !isset($page->getUrl()['subpages'][1])
            || !is_numeric($page->getUrl()['subpages'][0])
            || !UserController::checkToken($page->getUrl()['subpages'][0], $page->getUrl()['subpages'][1], 'reset')
        ) {
            FormController::alert('The link is invalid! Please follow the link in the email you received.', 'error', 'forgot-password', 2);
            return;
        }

        // Check if the reset password form is submitted
        if (isset($_POST['submit'])) {
            // Check if all the required fields are entered
            if (empty($_POST['new-password'])) {
                FormController::alert('Please enter your password!', 'warning', 'reset-password');
                return;
            }
            if (empty($_POST['new-password-check'])) {
                FormController::alert('Please confirm your password!', 'warning', 'reset-password');
                return;
            }

            // Check if the values entered in fields are not too long
            if (strlen($_POST['new-password']) > 50) {
                $_POST['new-password'] = '';
                FormController::alert('The input of the password field is too long!', 'warning', 'reset-password');
                return;
            }
            if (strlen($_POST['new-password-check']) > 50) {
                $_POST['new-password-check'] = '';
                FormController::alert('The input of the password field is too long!', 'warning', 'reset-password');
                return;
            }

            // Check if the password contains at least 8 characters, 1 uppercase letter, 1 lowercase letter and 1 number
            if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $_POST['new-password'])) {
                $_POST['new-password'] = '';
                $_POST['new-password-check'] = '';
                FormController::alert('Your password must contain at least 8 characters, 1 uppercase letter, 1 lowercase letter and 1 number!', 'warning', 'reset-password');
                return;
            }

            // Check if the password and password check are the same
            if ($_POST['new-password'] != $_POST['new-password-check']) {
                FormController::alert('The entered passwords do not match!', 'warning', 'reset-password');
                return;
            }

            // Reset the password
            $this->resetPassword($page->getUrl()['subpages'][0], $_POST['new-password']);
        }
    }

    /**
     * This method is for resetting the user's password.
     *
     * @param int $id
     * @param string $password
     *
     * @return void
     */
    private function resetPassword(int $id, string $password): void
    {
        $db = new Database();

        // Update the password in the database for the user
        $db->query('UPDATE users SET password = :password WHERE id = :id');
        $db->bind(':password', password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]));
        $db->bind(':id', $id);
        $db->execute();

        // Delete the token from the database
        $db->query('DELETE FROM tokens WHERE user_id = :user_id AND type = :type');
        $db->bind(':user_id', $id);
        $db->bind(':type', 'reset');
        $db->execute();

        // Redirect the user to the login page
        FormController::alert('Success! Your password has been reset!', 'success', 'login', 2);
    }
}
