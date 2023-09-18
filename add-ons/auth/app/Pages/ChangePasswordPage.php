<?php

namespace app\Pages;

use app\Controllers\FormController;
use app\Database\Database;

class ChangePasswordPage
{
    public function __construct()
    {
        // Check if the change password form is submitted
        if (isset($_POST['submit'])) {
            // Check if all the required fields are entered
            if (empty($_POST['old-password'])) {
                FormController::alert('Please enter your old password!', 'warning', 'change-password');
                return;
            }
            if (empty($_POST['new-password'])) {
                FormController::alert('Please enter your new password!', 'warning', 'change-password');
                return;
            }
            if (empty($_POST['new-password-check'])) {
                FormController::alert('Please confirm your new password!', 'warning', 'change-password');
                return;
            }

            // Check if the values entered in fields are not too long
            if (strlen($_POST['old-password']) > 50) {
                $_POST['old-password'] = '';
                FormController::alert('The input of the old password field is too long!', 'warning', 'change-password');
                return;
            }
            if (strlen($_POST['new-password']) > 50) {
                $_POST['new-password'] = '';
                FormController::alert('The input of the new password field is too long!', 'warning', 'change-password');
                return;
            }
            if (strlen($_POST['new-password-check']) > 50) {
                $_POST['new-password-check'] = '';
                FormController::alert('The input of the new password check field is too long!', 'warning', 'change-password');
                return;
            }

            // Check if the old password is correct
            if (!password_verify($_POST['old-password'], $_SESSION['user']['password'])) {
                FormController::alert('The old password is incorrect!', 'warning', 'change-password');
                return;
            }

            // Check if the new password contains at least 8 characters, 1 uppercase letter, 1 lowercase letter and 1 number
            if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $_POST['new-password'])) {
                $_POST['new-password'] = '';
                $_POST['new-password-check'] = '';
                FormController::alert('Your new password must contain at least 8 characters, 1 uppercase letter, 1 lowercase letter and 1 number!', 'warning', 'register');
                return;
            }

            // Check if the new password is the same as the old password
            if ($_POST['old-password'] === $_POST['new-password']) {
                FormController::alert('The new password is the same as the old password!', 'warning', 'change-password');
                return;
            }

            // Check if the new password and the new password check are the same
            if ($_POST['new-password'] !== $_POST['new-password-check']) {
                FormController::alert('The newly entered passwords do not match!', 'warning', 'change-password');
                return;
            }

            $this->changePassword($_SESSION['user']['id'], $_POST['new-password']);
        }
    }

    /**
     * This method is for changing the password of a user.
     *
     * @param int $id
     * @param string $password
     *
     * @return void
     */
    private function changePassword(int $id, string $password): void
    {
        $db = new Database();

        // Update the password in the database for the user
        $db->query('UPDATE users SET password = :password WHERE id = :id');
        $db->bind(':password', password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]));
        $db->bind(':id', $id);
        $db->execute();

        // Redirect the user to the profile page
        FormController::alert('Success! Your password has been changed!', 'success', 'profile', 2);
    }
}
