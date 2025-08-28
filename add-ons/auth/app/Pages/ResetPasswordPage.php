<?php

namespace app\Pages;

use app\Controllers\AuthController;
use app\Controllers\FormController;
use app\Controllers\PageController;
use app\Database\Database;
use app\Enums\AlertType;
use app\Models\Page;

class ResetPasswordPage
{
    public bool $disableForm = false;

    public function __construct(Page $page)
    {
        // Check if a user id and a token are given in the url, if the user id is a number and if the given user id and token are in the database
        if (!isset($page->urlArr['subpages'][0], $page->urlArr['subpages'][1])
            || !is_numeric($page->urlArr['subpages'][0])
            || !AuthController::checkToken($page->urlArr['subpages'][0], $page->urlArr['subpages'][1], 'reset')
        ) {
            // If not, disable the form and show an error message
            $this->disableForm = true;
            FormController::addAlert('The link is invalid! Please follow the link in the email you received.', AlertType::ERROR);
            return;
        }

        // Check if the reset password form is submitted
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) $this->post($page);
    }

    /**
     * This method is for handling the POST request of the reset password form.
     *
     * @param Page $page
     */
    private function post(Page $page): void
    {
        $valid = true;

        // Validate the form fields
        if (!FormController::validate('new-password', ['required', 'maxLength' => 50])) $valid = false;
        if (!FormController::validate('new-password-check', ['required', 'maxLength' => 50])) $valid = false;

        if (!$valid) return;

        // Check if the password contains at least 8 characters, 1 uppercase letter, 1 lowercase letter and 1 number
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $_POST['new-password'])) {
            $_POST['new-password'] = '';
            $_POST['new-password-check'] = '';

            FormController::addAlert('Your password must contain at least 8 characters, 1 uppercase letter, 1 lowercase letter and 1 number!', AlertType::WARNING);
            return;
        }

        // Check if the password and password check are the same
        if ($_POST['new-password'] !== $_POST['new-password-check']) {
            FormController::addAlert('The entered passwords do not match!', AlertType::WARNING);
            return;
        }

        // Reset the password
        $this->resetPassword($page->urlArr['subpages'][0], $_POST['new-password']);
    }

    /**
     * This method is for resetting the user's password.
     *
     * @param int $id
     * @param string $password
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

        // Show a success message and redirect to the login page
        FormController::addAlert('Success! Your password has been reset!', AlertType::SUCCESS);
        PageController::redirect('login', 2);
    }
}
