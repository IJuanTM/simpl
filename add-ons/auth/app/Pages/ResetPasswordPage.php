<?php

namespace app\Pages;

use app\Controllers\AuthController;
use app\Controllers\FormController;
use app\Controllers\PageController;
use app\Database\DB;
use app\Enums\AlertType;
use app\Models\Page;

class ResetPasswordPage
{
    public bool $disableForm = false;

    public function __construct(Page $page)
    {
        // Check if a user id and a token are given in the url and if the user id is a number
        if (
            !isset($page->urlArr['subpages'][0], $page->urlArr['subpages'][1]) ||
            !is_numeric($page->urlArr['subpages'][0])
        ) {
            $this->disableForm = true;

            // Check if a reset request exists for this user
            if (!$this->resetRequested($page->urlArr['subpages'][0])) {
                FormController::addAlert('No valid password reset request found for this user! Please try again.', AlertType::ERROR);
                PageController::redirect('forgot-password', 4);
                return;
            }

            // Check if the given token is valid
            if (!AuthController::checkToken($page->urlArr['subpages'][0], $page->urlArr['subpages'][1], 'reset')) {
                FormController::addAlert('The link is invalid! Please follow the link in the email you received.', AlertType::ERROR);
                return;
            }
        }

        // Check if the reset password form is submitted
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) $this->post($page);
    }

    /**
     * This method checks if a token of a specific type exists for a user.
     *
     * @param string $userId
     *
     * @return bool
     */
    private function resetRequested(string $userId): bool
    {
        // Check if the token exists in the database
        return DB::exists(
            'tokens',
            ['user_id' => $userId, 'type' => 'reset']
        );
    }

    /**
     * This method is for handling the POST request of the reset password form.
     *
     * @param Page $page
     */
    private function post(Page $page): void
    {
        // Validate the form fields
        if (
            !FormController::validate('new-password', ['required', 'maxLength' => 50]) ||
            !FormController::validate('new-password-check', ['required', 'maxLength' => 50])
        ) return;

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
        // Update the password in the database for the user
        DB::update(
            'users',
            ['password' => password_hash($password, PASSWORD_HASH_ALGO, PASSWORD_HASH_OPTIONS)],
            compact('id')
        );

        // Delete the token from the database
        DB::delete(
            'tokens',
            ['user_id' => $id, 'type' => 'reset']
        );

        // Show a success message and redirect to the login page
        FormController::addAlert('Success! Your password has been reset!', AlertType::SUCCESS);
        PageController::redirect('login', 2);
    }
}
