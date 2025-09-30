<?php

namespace app\Pages;

use app\Controllers\AlertController;
use app\Controllers\AuthController;
use app\Controllers\FormController;
use app\Controllers\PageController;
use app\Controllers\SessionController;
use app\Database\Database;
use app\Enums\AlertType;

/**
 * Handles password change functionality for authenticated users.
 */
class ChangePasswordPage
{
    public function __construct()
    {
        // Check if the change password form is submitted
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) $this->post();
    }

    /**
     * Processes password change form submission.
     */
    private function post(): void
    {
        // Validate the form fields
        if (
            !FormController::validate('old-password', ['required', 'maxLength' => 50]) ||
            !FormController::validate('new-password', ['required', 'maxLength' => 50]) ||
            !FormController::validate('new-password-check', ['required', 'maxLength' => 50])
        ) return;

        // Check if the old password is correct
        if (!AuthController::checkPassword(SessionController::get('user')['email'], $_POST['old-password'])) {
            $_POST['old-password'] = '';

            FormController::addAlert('The old password is incorrect!', AlertType::WARNING);
            return;
        }

        // Validate the new password against the password policy
        if (!AuthController::validatePassword($_POST['new-password'])) {
            $_POST['new-password'] = '';
            $_POST['new-password-check'] = '';
            return;
        }

        // Check if the new password is the same as the old password
        if ($_POST['old-password'] === $_POST['new-password']) {
            FormController::addAlert('The new password is the same as the old password!', AlertType::WARNING);
            return;
        }

        // Check if the new passwords match
        if ($_POST['new-password'] !== $_POST['new-password-check']) {
            FormController::addAlert('The newly entered passwords do not match!', AlertType::WARNING);
            return;
        }

        $this->changePassword(SessionController::get('user')['id'], $_POST['new-password']);
    }

    /**
     * Updates user's password in the database.
     *
     * @param int $id User ID
     * @param string $password New password (will be hashed)
     */
    private function changePassword(int $id, string $password): void
    {
        $db = new Database();

        // Update the password in the database for the user
        $db->query('UPDATE users SET password = :password WHERE id = :id');
        $db->bind(':password', password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]));
        $db->bind(':id', $id);
        $db->execute();

        // Redirect to the profile page with a success message
        PageController::redirect('profile');
        AlertController::alert('Success! Your password has been changed!', AlertType::SUCCESS, 4);
    }
}
