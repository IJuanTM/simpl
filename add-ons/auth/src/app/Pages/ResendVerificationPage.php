<?php

declare(strict_types=1);

namespace app\Pages;

use app\Controllers\AuthController;
use app\Controllers\PageController;
use app\Enums\AlertType;
use app\Models\Page;
use app\Utils\RateLimiter;

/**
 * Generates and sends a new verification token for the user named in the URL parameter, then redirects with appropriate feedback.
 * Only responds to CSRF-protected POST requests, so a forged cross-site GET can't spend the resend budget on a victim's account.
 */
class ResendVerificationPage
{
    private Page $page;

    public function __construct(Page $page)
    {
        $this->page = $page;
    }

    /**
     * Only ever reached via /api/resend-verification/{public_id}.
     * PageController requires an api() method to exist on any page dispatched through /api/...,
     * or it 404s instead of rendering this page's redirect.
     *
     * @return void
     */
    public function api(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            PageController::redirect(REDIRECT);
            return;
        }

        $publicId = $this->page->subpage() ?? '';
        $id = AuthController::getUserIdByPublicId($publicId);

        if ($id === null) {
            PageController::redirectWithToast(REDIRECT, 'Undefined user id! Please contact an administrator.', AlertType::ERROR, 0, 2);
            return;
        }

        if (!RateLimiter::attempt(RateLimiter::ipKey('resend-verification-ip'), VERIFICATION_CONFIG['resend_ip_max_attempts'], VERIFICATION_CONFIG['resend_ip_attempt_window'])) {
            PageController::redirectWithToast(REDIRECT, 'Please wait a moment before requesting another verification email!', AlertType::WARNING, 0, 2);
            return;
        }

        // The account-scoped limit fails silently behind the same generic response as a genuine resend, unlike the IP limit above.
        // A party who only knows this account's id can't use a distinct "too many attempts" response to confirm they're suppressing its real resends.
        if (RateLimiter::attempt('resend-verification-' . $id, 1, RESEND_TIMEOUTS['verification']) && !AuthController::isVerified($id)) $this->resendVerification($id, $publicId);
        else PageController::redirectWithToast("verify-account/$publicId", 'If your account needs verification, a new email has been sent.', AlertType::INFO, 4);
    }

    /**
     * Generates new verification token and sends verification email.
     *
     * @param int    $id       User ID
     * @param string $publicId The same user's public id, for the redirect back to its verify page
     *
     * @return void
     */
    private function resendVerification(int $id, string $publicId): void
    {
        if (AuthController::issueVerificationToken($id, AuthController::getUserById($id)['email'])) PageController::redirectWithToast("verify-account/$publicId", 'If your account needs verification, a new email has been sent.', AlertType::INFO, 4);
        else PageController::redirectWithToast("verify-account/$publicId", 'An error occurred while sending your verification email! Please contact support.', AlertType::ERROR, 8);
    }
}
