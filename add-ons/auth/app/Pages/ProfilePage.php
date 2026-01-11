<?php

namespace app\Pages;

use app\Controllers\AlertController;
use app\Controllers\AuthController;
use app\Controllers\FormController;
use app\Controllers\MailController;
use app\Controllers\PageController;
use app\Controllers\SessionController;
use app\Database\DB;
use app\Enums\AlertType;
use app\Enums\ErrorCode;
use app\Models\Page;
use app\Models\Url;

/**
 * Handles user profile management functionality.
 */
class ProfilePage
{
    public function __construct()
    {
        // Check if the user is logged in
        if (!SessionController::get('user')) {
            PageController::error(ErrorCode::FORBIDDEN);
            exit;
        }

        // Check if the profile form is submitted
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) $this->post();
    }

    /**
     * Processes profile update form submission.
     */
    private function post(): void
    {
        // Validate the form fields
        if (
            !FormController::validate('username', ['maxLength' => 100]) ||
            !FormController::validate('email', ['required', 'maxLength' => 100, 'type' => 'email'])
        ) return;

        // Sanitize the email
        $_POST['email'] = FormController::sanitize($_POST['email']);

        // Check if the email is changed and if it is already in use by another user
        if (SessionController::get('user')['email'] !== $_POST['email'] && AuthController::checkEmail($_POST['email'])) {
            $_POST['email'] = SessionController::get('user')['email'];

            FormController::addAlert('An account with this email already exists!', AlertType::WARNING);
            return;
        }

        // Update the user
        $this->update(
            SessionController::get('user')['id'],
            FormController::sanitize($_POST['username']),
            $_POST['email']
        );
    }

    /**
     * Updates user profile information in the database.
     *
     * @param int $id User ID
     * @param string $username New username
     * @param string $email New email address
     */
    private function update(int $id, string $username, string $email): void
    {
        // Update the username in the database
        DB::update(
            'users',
            compact('username'),
            compact('id')
        );

        // Check if the email has changed
        if (SessionController::get('user')['email'] !== $email) {
            // Update the email in the database
            DB::update(
                'users',
                compact('email'),
                compact('id')
            );

            if (EMAIL_VERIFICATION_REQUIRED) {
                // Generate a verification token
                $token = AuthController::generateToken(8);

                // Set the verification token in the database
                DB::insert(
                    'tokens',
                    [
                        'user_id' => $id,
                        compact('token'),
                        'type' => 'verification'
                    ]
                );

                // Send a verification email to the user
                $this->verificationMail($id, $email, $token);
                return;
            }
        }

        // Get the updated user from the database
        $user = DB::single(
            '*',
            'users',
            compact('id')
        );

        // Add the role to the user array
        $user += ['role' => SessionController::get('user')['role']];

        // Update the user session
        SessionController::set('user', $user);

        // Redirect to the profile page with a success message
        PageController::redirect('profile');
        AlertController::alert('Success! Your profile has been updated!', AlertType::SUCCESS, 4);
    }

    /**
     * Sends verification email after email address change.
     *
     * @param int $id User ID
     * @param string $to New email address
     * @param string $code Verification code
     */
    private function verificationMail(int $id, string $to, string $code): void
    {
        // Get the template from the views/parts/mails folder
        $contents = MailController::template('verification', [
            'title' => 'Verify New Email Address - ' . APP_NAME,
            'link' => Url::to("verify-account/$id/$code"),
            'code' => $code
        ]);

        // Check if template was loaded successfully
        if ($contents === false) {
            FormController::addAlert('An error occurred while sending your verification email! Please contact support.', AlertType::ERROR);
            return;
        }

        // Send the message
        $result = MailController::send(APP_NAME, $to, NO_REPLY_MAIL, 'Verify account', $contents);

        if ($result) {
            // Redirect to the logout page with a success message
            PageController::redirect('api/logout');
            AlertController::alert('Success! Your profile has been updated! Please verify your new email address!', AlertType::SUCCESS, 4);
        } else FormController::addAlert('An error occurred while sending your verification email! Please contact support.', AlertType::ERROR);
    }

    /**
     * Handles API requests for profile-related actions.
     *
     * @param Page $page Page object with request information
     */
    final public function api(Page $page): void
    {
        // Check if the user is trying to perform an action related to the profile image
        if (isset($page->urlArr['subpages'][0])) {
            switch ($page->urlArr['subpages'][0]) {
                case 'update-profile-image':
                    self::updateProfileImage();
                    break;
                case 'delete-profile-image':
                    self::deleteProfileImage();
                    break;
            }
        }
    }

    /**
     * Processes profile image upload and update.
     */
    private static function updateProfileImage(): void
    {
        // Check if the file is uploaded correctly
        if (!isset($_FILES['new_img']) || $_FILES['new_img']['error'] !== UPLOAD_ERR_OK) {
            // Redirect to the profile page with an error message
            PageController::redirect('profile');
            AlertController::alert('Image upload failed. Please try again.', AlertType::ERROR, 4);
            return;
        }

        $file = $_FILES['new_img'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        // Get the mime type of the file
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        // Validate if the file is an image by checking the mime type
        if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            PageController::redirect('profile');
            AlertController::alert('The uploaded file is not a valid image type.', AlertType::ERROR, 4);
            return;
        }

        // Validate if the file is an image by checking the image size
        if (getimagesize($file['tmp_name']) === false) {
            PageController::redirect('profile');
            AlertController::alert('The uploaded file is not a valid image.', AlertType::ERROR, 4);
            return;
        }

        // Check if the image is too large
        if ($file['size'] > 2 * 1024 * 1024) {
            // Redirect to the profile page with an error message
            PageController::redirect('profile');
            AlertController::alert('The image size is too large. Please choose an image that is less than 2MB.', AlertType::ERROR, 4);
            return;
        }

        $id = SessionController::get('user')['id'];
        $path = $_SERVER['DOCUMENT_ROOT'] . '/img/profile/';

        // Fetch the old image name from the database
        $old = DB::single(
            'profile_img',
            'users',
            compact('id')
        )['profile_img'] ?? null;

        // Remove the old image if it exists
        if ($old) {
            $oldPath = $path . $old;

            // Remove the image if it exists
            if (is_file($oldPath)) unlink($oldPath);
        }

        $name = "{$id}_" . time() . ".$extension";

        // Move the new image to the profile folder
        move_uploaded_file($file['tmp_name'], $path . $name);

        // Update the database
        DB::update(
            'users',
            [
                'profile_img' => $name
            ],
            compact('id')
        );

        // Redirect to the profile page with a success message
        PageController::redirect('profile');
        AlertController::alert('Profile image updated successfully!', AlertType::SUCCESS, 4);
    }

    /**
     * Removes a user's profile image.
     */
    private static function deleteProfileImage(): void
    {
        $id = SessionController::get('user')['id'];

        // Fetch the old image name from the database
        $old = DB::single(
            'profile_img',
            'users',
            compact('id')
        )['profile_img'] ?? null;

        // Remove the old image if it exists
        if ($old) {
            $oldPath = $_SERVER['DOCUMENT_ROOT'] . '/img/profile/' . $old;

            // Remove the image if it exists
            if (is_file($oldPath)) unlink($oldPath);
        }

        // Remove the profile image from the database
        DB::update(
            'users',
            [
                'profile_img' => null
            ],
            compact('id')
        );

        // Redirect to the profile page with a success message
        PageController::redirect('profile');
        AlertController::alert('Profile image deleted successfully!', AlertType::SUCCESS, 4);
    }
}
