<?php

namespace app\Pages;

use app\Controllers\{AppController, FormController, MailController, UserController};
use app\Database\Database;
use app\Models\PageModel;

class ResendVerificationPage
{
    public function __construct(PageModel $page)
    {
        // Get the user id from the url and sanitize it
        $id = AppController::sanitize($page->getUrl()['subpages'][0] ?? '');

        // Check if the user id is not empty and is numeric
        if (empty($id) || !is_numeric($id)) {
            FormController::alert('Undefined user id! Please contact an administrator.', 'error', REDIRECT, 2);
            return;
        }

        // Check if the user exists in the database
        if (!UserController::exists($id)) {
            FormController::alert('We could not find your account! Please contact an administrator.', 'error', REDIRECT, 2);
            return;
        }

        // Check if the user is trying to verify their account
        if (UserController::isVerified($id)) {
            FormController::alert('Your account is currently not being verified!', 'info', REDIRECT, 2);
            return;
        }

        // Resend the verification code
        $this->resendVerification($id);

        // Redirect the user to the verify page
        FormController::alert('A new verification code has been send to your email!', 'success', "verify-account/$id", 2);
    }

    /**
     * This method is for resending the verification token to the user.
     *
     * @param int $id
     *
     * @return void
     */
    private function resendVerification(int $id): void
    {
        $db = new Database();

        // Generate a new verification token
        $token = UserController::generateToken(4);

        // Update the code in the database for the user
        $db->query('UPDATE tokens SET token = :token WHERE user_id = :id AND type = :type');
        $db->bind(':id', $id);
        $db->bind(':token', $token);
        $db->bind(':type', 'verification');
        $db->execute();

        // Get the email of the user
        $db->query('SELECT email FROM users WHERE id = :id');
        $db->bind(':id', $id);

        // Send a verification email to the user
        MailController::verification($id, $db->single()['email'], $token);

        // Redirect the user to the verification page
        FormController::alert('Success! A new verification token has been sent to your email!', 'success', "verify-account/$id", 2);
    }
}
