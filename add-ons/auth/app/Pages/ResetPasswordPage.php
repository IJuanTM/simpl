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
        $id = $page->subpage();
        $token = $page->subpage(1);

        // A valid reset link must carry a numeric user id and a token
        if ($id === null || $token === null || !is_numeric($id)) {
            $this->disableForm = true;
            FormController::addAlert('The link is invalid! Please follow the link in the email you received.', AlertType::ERROR);
            PageController::redirect('forgot-password', 4);
            return;
        }

        $id = (int)$id;

        // A reset request must exist for this user
        if (!DB::exists(
            'tokens',
            [
                'user_id' => $id,
                'type' => 'reset'
            ]
        )) {
            $this->disableForm = true;
            FormController::addAlert('No valid password reset request found for this user! Please try again.', AlertType::ERROR);
            PageController::redirect('forgot-password', 4);
            return;
        }

        // The token must match before any password change is allowed (checked on both GET and POST)
        if (!AuthController::checkToken($id, $token, 'reset')) {
            $this->disableForm = true;
            FormController::addAlert('The link is invalid! Please follow the link in the email you received.', AlertType::ERROR);
            return;
        }

        // Token is valid; process password reset form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) $this->post($id);
    }

    /**
     * Processes password reset form submission.
     *
     * @param int $id Verified user ID from the reset link
     *
     * @return void
     */
    private function post(int $id): void
    {
        // Validate form fields
        if (
            !FormController::validate('new-password', ['required', 'maxLength' => MAX_PASSWORD_LENGTH]) ||
            !FormController::validate('new-password-check', ['required', 'maxLength' => MAX_PASSWORD_LENGTH])
        ) return;

        // Validate password against policy
        if (!FormController::validatePasswords('new-password', 'new-password-check')) return;

        // Reset password
        $this->resetPassword($id, $_POST['new-password']);
    }

    /**
     * Updates user password and deletes reset token.
     *
     * @param int    $id       User ID
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
