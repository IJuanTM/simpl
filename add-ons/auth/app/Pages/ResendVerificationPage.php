<?php

declare(strict_types=1);

namespace app\Pages;

use app\Controllers\AppController;
use app\Controllers\AuthController;
use app\Controllers\FormController;
use app\Controllers\PageController;
use app\Enums\AlertType;
use app\Models\Page;
use app\Utils\RateLimiter;

/**
 * ResendVerificationPage
 *
 * Generates and sends a new verification token for a user identified by the
 * URL parameter and redirects the user with appropriate feedback.
 */
class ResendVerificationPage
{
    public function __construct(Page $page)
    {
        // Get and sanitize user ID from URL
        $id = AppController::sanitize($page->subpage() ?? '');

        // Validate user ID
        if (empty($id) || !is_numeric($id)) {
            FormController::addAlert('Undefined user id! Please contact an administrator.', AlertType::ERROR);
            PageController::redirect(REDIRECT, 2);
            return;
        }

        $id = (int)$id;

        // Check if user exists
        if (!AuthController::exists($id)) {
            FormController::addAlert('We could not find your account! Please contact an administrator.', AlertType::ERROR);
            PageController::redirect(REDIRECT, 2);
            return;
        }

        // Check if account still needs verification
        if (AuthController::isVerified($id)) {
            FormController::addAlert('Your account is currently not being verified!', AlertType::INFO);
            PageController::redirect(REDIRECT, 2);
            return;
        }

        // Rate limit resend attempts, max 1 in set timeframe
        if (!RateLimiter::attempt('resend-verification-' . $id, 1, VERIFICATION_RESEND_TIMEOUT)) {
            FormController::addAlert('Please wait a moment before requesting another verification email!', AlertType::WARNING);
            PageController::redirect(REDIRECT, 2);
            return;
        }

        $this->resendVerification($id);
    }

    /**
     * Generates new verification token and sends verification email.
     *
     * @param int $id User ID
     *
     * @return void
     */
    private function resendVerification(int $id): void
    {
        $email = AuthController::getUserById($id)['email'];

        $result = AuthController::issueVerificationToken($id, $email);
        if ($result) PageController::redirectWithAlert("verify-account/$id", 'Success! A new verification email has been sent!', AlertType::SUCCESS, 4);
        else PageController::redirectWithAlert("verify-account/$id", 'An error occurred while sending your verification email! Please contact support.', AlertType::ERROR, 8);
    }
}
