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
    private Page $page;

    public function __construct(Page $page)
    {
        $this->page = $page;
    }

    /**
     * Only ever reached via /api/resend-verification/{id}.
     * PageController requires an api() method to exist on any page dispatched through /api/...,
     * or it 404s instead of rendering this page's redirect.
     *
     * @return void
     */
    public function api(): void
    {
        $id = AppController::sanitize($this->page->subpage() ?? '');

        if (empty($id) || !is_numeric($id)) {
            FormController::addAlert('Undefined user id! Please contact an administrator.', AlertType::ERROR);
            PageController::redirect(REDIRECT, 2);
            return;
        }

        $id = (int)$id;

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        if (!RateLimiter::attempt("resend-verification-ip-$ip", VERIFICATION_CONFIG['resend_ip_max_attempts'], VERIFICATION_CONFIG['resend_ip_attempt_window'])) {
            FormController::addAlert('Please wait a moment before requesting another verification email!', AlertType::WARNING);
            PageController::redirect(REDIRECT, 2);
            return;
        }

        // The account-scoped limit fails silently behind the same generic response as a genuine resend, unlike the IP limit above.
        // A party who only knows this account's id can't use a distinct "too many attempts" response to confirm they're suppressing its real resends.
        $withinAccountLimit = RateLimiter::attempt('resend-verification-' . $id, 1, RESEND_TIMEOUTS['verification']);

        if ($withinAccountLimit && AuthController::needsVerification($id)) $this->resendVerification($id);
        else PageController::redirectWithAlert("verify-account/$id", 'If your account needs verification, a new email has been sent.', AlertType::INFO, 4);
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
        if ($result) PageController::redirectWithAlert("verify-account/$id", 'If your account needs verification, a new email has been sent.', AlertType::INFO, 4);
        else PageController::redirectWithAlert("verify-account/$id", 'An error occurred while sending your verification email! Please contact support.', AlertType::ERROR, 8);
    }
}
