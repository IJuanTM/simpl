<?php

namespace app\Pages;

use app\Controllers\AuthController;
use app\Controllers\FormController;
use app\Controllers\PageController;
use app\Database\DB;
use app\Enums\AlertType;
use app\Models\Page;

/**
 * Handles resending verification emails to users.
 */
class ResendVerificationPage
{
    /**
     * Validates user ID and initiates verification email resend process.
     *
     * @param Page $page Page object containing URL parameters
     */
    public function __construct(Page $page)
    {
        // Get the user id from the url and sanitize it
        $id = FormController::sanitize($page->urlArr['subpages'][0] ?? '');

        // Check if the user id is not empty and is numeric
        if (empty($id) || !is_numeric($id)) {
            FormController::addAlert('Undefined user id! Please contact an administrator.', AlertType::ERROR);
            PageController::redirect(REDIRECT, 2);
            return;
        }

        // Check if the user exists in the database
        if (!AuthController::exists($id)) {
            FormController::addAlert('We could not find your account! Please contact an administrator.', AlertType::ERROR);
            PageController::redirect(REDIRECT, 2);
            return;
        }

        // Check if the user is trying to verify their account
        if (AuthController::isVerified($id)) {
            FormController::addAlert('Your account is currently not being verified!', AlertType::INFO);
            PageController::redirect(REDIRECT, 2);
            return;
        }

        // Resend the verification code
        $this->resendVerification($id);
    }

    /**
     * Generates new verification token and updates database.
     *
     * @param int $id User ID
     */
    private function resendVerification(int $id): void
    {
        // Generate a new verification token
        $token = AuthController::generateToken(4);

        // Update the code in the database for the user
        DB::update(
            'tokens',
            compact('token'),
            [
                'user_id' => $id,
                'type' => 'verification'
            ]
        );

        // Send a verification email to the user
        AuthController::sendVerificationMail(
            $id,
            DB::single(
                'email',
                'users',
                compact('id')
            )['email'],
            $token,
            true
        );
    }
}
