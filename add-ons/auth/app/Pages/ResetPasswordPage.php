<?php

declare(strict_types=1);

namespace app\Pages;

use app\Controllers\AuthController;
use app\Controllers\FormController;
use app\Controllers\PageController;
use app\Database\DB;
use app\Enums\AlertType;
use app\Models\Page;

/**
 * ResetPasswordPage
 *
 * Handles password reset via tokenized links and processes the reset form.
 */
class ResetPasswordPage
{
    public bool $disableForm = false;

    public function __construct(Page $page)
    {
        // Validate URL parameters
        if ($page->subpage() === null || $page->subpage(1) === null || !is_numeric($page->subpage())) {
            $this->disableForm = true;

            // Check if reset request exists
            if (!DB::exists(
                'tokens',
                [
                    'user_id' => $page->subpage(),
                    'type' => 'reset'
                ]
            )) {
                FormController::addAlert('No valid password reset request found for this user! Please try again.', AlertType::ERROR);
                PageController::redirect('forgot-password', 4);
                return;
            }

            // Validate reset token
            if (!AuthController::checkToken($page->subpage(), $page->subpage(1), 'reset')) {
                FormController::addAlert('The link is invalid! Please follow the link in the email you received.', AlertType::ERROR);
                return;
            }
        }

        // Process password reset form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) $this->post($page);
    }

    /**
     * Processes password reset form submission.
     *
     * @param Page $page Page object with URL parameters
     *
     * @return void
     */
    private function post(Page $page): void
    {
        // Validate form fields
        if (
            !FormController::validate('new-password', ['required', 'maxLength' => 50]) ||
            !FormController::validate('new-password-check', ['required', 'maxLength' => 50])
        ) return;

        // Validate password against policy
        if (!FormController::validatePasswords('new-password', 'new-password-check')) return;

        // Reset password
        $this->resetPassword($page->subpage(), $_POST['new-password']);
    }

    /**
     * Updates user password and deletes reset token.
     *
     * @param int    $id User ID
     * @param string $password New password
     *
     * @return void
     */
    private function resetPassword(int $id, string $password): void
    {
        // Update password in database
        AuthController::updatePassword($id, $password);

        // Delete reset token
        AuthController::deleteToken($id, 'reset');

        // Redirect to login with success message
        FormController::addAlert('Success! Your password has been reset!', AlertType::SUCCESS);
        PageController::redirect('login', 2);
    }
}
