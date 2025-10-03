<?php

namespace app\Pages;

use app\Controllers\AlertController;
use app\Controllers\AuthController;
use app\Controllers\FormController;
use app\Controllers\PageController;
use app\Database\Database;
use app\Enums\AlertType;
use app\Models\Page;

/**
 * The VerifyPage class is the controller for the verify page.
 * It is used to verify a new registered user.
 */
class VerifyAccountPage
{
    public function __construct(Page $page)
    {
        // Get the user id from the url and sanitize it
        $id = FormController::sanitize($page->urlArr['subpages'][0] ?? '');

        // Check if the user id is not empty and is numeric
        if (empty($id) || !is_numeric($id)) {
            FormController::addAlert('Undefined user id! Please check your mail.', AlertType::ERROR);
            PageController::redirect(REDIRECT, 2);
            return;
        }

        // Check if the user exists in the database
        if (!AuthController::exists($id)) {
            FormController::addAlert('We could not find your account! Please check your mail.', AlertType::ERROR);
            PageController::redirect(REDIRECT, 2);
            return;
        }

        // Check if the user has already been verified
        if (AuthController::isVerified($id)) {
            FormController::addAlert('Your account has already been verified!', AlertType::INFO);
            PageController::redirect('login', 2);
            return;
        }

        // Sanitize the code send with the url
        $code = FormController::sanitize($page->urlArr['subpages'][1] ?? '');

        // Check if the code is not empty and if it is, check if the code is sent with the form
        if (!empty($code)) {
            // Check if the code field is not too long
            if (strlen($code) > 8) {
                FormController::addAlert('The verification code given in the url is too long!', AlertType::WARNING);
                return;
            }

            // Check if the code is correct
            if (!AuthController::checkToken($id, $code, 'verification')) {
                FormController::addAlert('The verification code given in the url is incorrect! Please check your mail.', AlertType::ERROR);
                return;
            }

            // Verify the user
            $this->verify($id);
        }

        // Check if the form is submitted
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) $this->post($id);
    }

    /**
     * This method is for verifying the user's account. It is called after all checks are done.
     * Here the system removes the verification token from the database and redirects the user to the login page.
     *
     * @param int $id
     */
    private function verify(int $id): void
    {
        $db = new Database();

        // Empty the code in the database for the user
        $db->query('DELETE FROM tokens WHERE user_id = :id AND type = :type');
        $db->bind(':id', $id);
        $db->bind(':type', 'verification');
        $db->execute();

        // Redirect the user to the login page and show a success message
        PageController::redirect('login');
        AlertController::alert('Success! Your account has been verified!', AlertType::SUCCESS, 4);
    }

    /**
     * This method is for handling the POST request of the verify account form.
     *
     * @param int $userId
     */
    private function post(int $userId): void
    {
        // Check if the token field is entered
        if (empty($_POST['code'])) {
            FormController::addAlert('Please enter the verification code received in your mail!', AlertType::WARNING);
            return;
        }

        // Check if the token field is not too long
        if (strlen($_POST['code']) > 8) {
            FormController::addAlert('The verification code is too long!', AlertType::WARNING);
            return;
        }

        // Check if the token is correct
        if (!AuthController::checkToken($userId, $_POST['code'], 'verification')) {
            FormController::addAlert('The verification code is incorrect!', AlertType::ERROR);
            return;
        }

        // Verify the user
        $this->verify($userId);
    }
}
