<?php

namespace app\Pages;

use app\Controllers\{AppController, FormController, PageController, UserController};
use app\Database\Database;
use app\Models\PageModel;

/**
 * The VerifyPage class is the controller for the verify page.
 * It is used to verify a new registered user.
 */
class VerifyAccountPage
{
    public function __construct(PageModel $page)
    {
        // Get the user id from the url and sanitize it
        $id = AppController::sanitize($page->getUrl()['subpages'][0] ?? '');

        // Check if the user id is not empty and is numeric
        if (empty($id) || !is_numeric($id)) {
            FormController::alert('Undefined user id! Please check your mail.', 'error', REDIRECT, 2);
            return;
        }

        // Check if the user exists in the database
        if (!UserController::exists($id)) {
            FormController::alert('We could not find your account! Please check your mail.', 'error', REDIRECT, 2);
            return;
        }

        // Check if the user has already been verified
        if (UserController::isVerified($id)) {
            FormController::alert('Your account has already been verified!', 'info', REDIRECT, 2);
            return;
        }

        // Sanitize the code send with the url
        $code = AppController::sanitize($page->getUrl()['subpages'][1] ?? '');

        // Check if the code is not empty and if it is, check if the code is send with the form
        if (!empty($code)) {
            // Check if the code field is not too long
            if (strlen($code) > 8) {
                FormController::alert('The verification code given in the url is too long!', 'warning', "verify-account/$id", 2);
                return;
            }

            // Check if the code is correct
            if (!UserController::checkToken($id, $code, 'verification')) {
                FormController::alert('The verification code given in the url is incorrect! Please check your mail.', 'error', "verify-account/$id", 2);
                return;
            }

            // Verify the user
            $this->verify($id);
        }

        // Check if the form is submitted
        if (isset($_POST['submit'])) {
            // Check if the token field is entered
            if (empty($_POST['code'])) {
                FormController::alert('Please enter the verification code received in your mail!', 'warning', "verify-account/$id");
                return;
            }

            // Check if the token field is not too long
            if (strlen($_POST['code']) > 8) {
                FormController::alert('The verification code is too long!', 'warning', "verify-account/$id");
                return;
            }

            // Check if the token is correct
            if (!UserController::checkToken($id, $_POST['code'], 'verification')) {
                FormController::alert('The verification code is incorrect!', 'error', "verify-account/$id");
                return;
            }

            // Verify the user
            $this->verify($id);
        }
    }

    /**
     * This method is for verifying the user's account. It is called after all checks are done.
     * Here the system removes the verification token from the database and redirects the user to the login page.
     *
     * @param int $id
     *
     * @return void
     */
    private function verify(int $id): void
    {
        $db = new Database();

        // Empty the code in the database for the user
        $db->query('DELETE FROM tokens WHERE user_id = :id AND type = :type');
        $db->bind(':id', $id);
        $db->bind(':type', 'verification');
        $db->execute();

        // Redirect the user to the login page
        PageController::redirect('login');

        // Set the success message
        AppController::alert('Success! Your account has been verified!', ['success', 'global'], 4);
    }
}
