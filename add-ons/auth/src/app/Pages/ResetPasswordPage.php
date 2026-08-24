<?php

declare(strict_types=1);

namespace app\Pages;

use app\Controllers\AuthController;
use app\Controllers\FormController;
use app\Controllers\PageController;
use app\Controllers\SessionController;
use app\Database\DB;
use app\Enums\AlertType;
use app\Enums\TokenType;
use app\Models\Page;
use app\Utils\RateLimiter;

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
            PageController::redirect('forgot-password', 2);
            return;
        }

        $id = (int)$id;

        // A reset request must exist for this user
        if (!DB::exists(
            'tokens',
            [
                'user_id' => $id,
                'type' => TokenType::RESET->value
            ]
        )) {
            $this->disableForm = true;
            FormController::addAlert('No valid password reset request found for this user! Please try again.', AlertType::ERROR);
            PageController::redirect('forgot-password', 2);
            return;
        }

        // A token verified earlier in this session only skips re-throttling, not the token check.
        // Reloading or resubmitting the form then can't burn the guess budget on a token that was never re-guessed.
        // The token itself is still re-checked below on every request, so expiry/revocation still apply.
        $sessionKey = "reset-token-verified-$id";
        $tokenHash = hash('sha256', strtoupper($token));
        $alreadyVerified = SessionController::get($sessionKey) === $tokenHash;

        if (!$alreadyVerified && $this->throttle($id)) {
            $this->disableForm = true;
            return;
        }

        if (!AuthController::checkToken($id, $token, TokenType::RESET)) {
            SessionController::remove($sessionKey);
            $this->disableForm = true;
            FormController::addAlert('The link is invalid! Please follow the link in the email you received.', AlertType::ERROR);
            return;
        }

        if (!$alreadyVerified) SessionController::set($sessionKey, $tokenHash);

        // Reached only once the link's user id and token pass all the checks above.
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) $this->post($id);
    }

    /**
     * Records a reset-token check attempt, checking per-account then per-IP limits.
     *
     * @param int $id User ID from the reset link
     *
     * @return bool True when either attempt limit has been exceeded
     */
    private function throttle(int $id): bool
    {
        if (!RateLimiter::attempt("reset-attempt-account-$id", PASSWORD_RESET_CONFIG['token_max_attempts'], PASSWORD_RESET_CONFIG['token_attempt_window'])) {
            FormController::addAlert('Too many reset attempts for this account. Please wait a while before trying again.', AlertType::ERROR);
            return true;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        if (!RateLimiter::attempt("reset-attempt-ip-$ip", PASSWORD_RESET_CONFIG['token_ip_max_attempts'], PASSWORD_RESET_CONFIG['token_ip_attempt_window'])) {
            FormController::addAlert('Too many reset attempts. Please wait a while before trying again.', AlertType::ERROR);
            return true;
        }

        return false;
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
        if (
            !FormController::validate('new-password', ['required', 'maxLength' => MAX_PASSWORD_LENGTH]) ||
            !FormController::validate('new-password-check', ['required', 'maxLength' => MAX_PASSWORD_LENGTH])
        ) return;

        if (!FormController::validatePasswords('new-password', 'new-password-check')) return;

        $this->resetPassword($id, $_POST['new-password']);
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
        AuthController::updatePassword($id, $password);

        AuthController::deleteToken($id, TokenType::RESET);
        SessionController::remove("reset-token-verified-$id");

        FormController::addAlert('Success! Your password has been reset!', AlertType::SUCCESS);
        PageController::redirect('login', 4);
    }
}
