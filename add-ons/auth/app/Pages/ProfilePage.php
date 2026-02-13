<?php

declare(strict_types=1);

namespace app\Pages;

use app\Controllers\AlertController;
use app\Controllers\AuthController;
use app\Controllers\FormController;
use app\Controllers\MailController;
use app\Controllers\PageController;
use app\Controllers\SessionController;
use app\Database\DB;
use app\Enums\AlertType;
use app\Models\Page;
use app\Models\Url;

/**
 * ProfilePage
 *
 * Handles viewing and updating the authenticated user's profile, including
 * profile image upload and verification email on email change.
 */
class ProfilePage
{
    public function __construct()
    {
        // Require authentication
        AuthController::requireAuth();

        // Process profile update form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) $this->post();
    }

    /**
     * Processes profile update form submission.
     *
     * @return void
     */
    private function post(): void
    {
        // Validate form fields
        if (
            !FormController::validate('username', ['maxLength' => 100]) ||
            !FormController::validate('first_name', ['maxLength' => 100]) ||
            !FormController::validate('infix', ['maxLength' => 50]) ||
            !FormController::validate('last_name', ['maxLength' => 100]) ||
            !FormController::validate('email', ['required', 'maxLength' => 100, 'type' => 'email'])
        ) return;

        // Sanitize all inputs
        FormController::sanitizeFields(['username', 'first_name', 'infix', 'last_name', 'email']);

        // Check if email changed and is already in use
        if (SessionController::get('user')['email'] !== $_POST['email'] && AuthController::checkEmail($_POST['email'])) {
            $_POST['email'] = SessionController::get('user')['email'];
            FormController::addAlert('An account with this email already exists!', AlertType::WARNING);
            return;
        }

        // Update profile
        $this->update(
            SessionController::get('user')['id'],
            $_POST['username'],
            $_POST['first_name'],
            $_POST['infix'],
            $_POST['last_name'],
            $_POST['email']
        );
    }

    /**
     * Updates user profile in database and handles email verification if email changed.
     *
     * @param int $id User ID
     * @param string $username Username
     * @param string $first_name First name
     * @param string $infix Name infix (e.g., "van", "de")
     * @param string $last_name Last name
     * @param string $email Email address
     *
     * @return void
     */
    private function update(int $id, string $username, string $first_name, string $infix, string $last_name, string $email): void
    {
        // Prepare update data (convert empty strings to null)
        $updateData = [
            'username' => !empty($username) ? $username : null,
            'first_name' => !empty($first_name) ? $first_name : null,
            'infix' => !empty($infix) ? $infix : null,
            'last_name' => !empty($last_name) ? $last_name : null
        ];

        // Update profile fields
        DB::update(
            'users',
            $updateData,
            compact('id')
        );

        // Check if email was changed
        if (SessionController::get('user')['email'] !== $email) {
            // Update email in database
            DB::update(
                'users',
                compact('email'),
                compact('id')
            );

            // If verification required, send new verification email
            if (EMAIL_VERIFICATION_REQUIRED) {
                // Generate verification token
                $token = AuthController::generateToken(8);

                // Store token in database
                AuthController::createToken($id, $token, 'verification');

                // Send verification email
                $this->verificationMail($id, $email, $token);
                return;
            }
        }

        // Get updated user data
        $user = AuthController::getUserById($id);

        // Preserve role in session
        $user['role'] = SessionController::get('user')['role'];

        // Update session
        SessionController::set('user', $user);

        // Redirect with success message
        PageController::redirect('profile');
        AlertController::globalAlert('Success! Your profile has been updated!', AlertType::SUCCESS, 4);
    }

    /**
     * Sends verification email after email address change.
     *
     * @param int $id User ID
     * @param string $to New email address
     * @param string $code Verification code
     *
     * @return void
     */
    private function verificationMail(int $id, string $to, string $code): void
    {
        // Get email template
        $contents = MailController::template('verification', [
            'title' => 'Verify New Email Address - ' . APP_NAME,
            'link' => Url::to("verify-account/$id/$code"),
            'code' => $code
        ]);

        // Check if template loaded successfully
        if ($contents === false) {
            FormController::addAlert('An error occurred while sending your verification email! Please contact support.', AlertType::ERROR);
            return;
        }

        // Send verification email
        $result = MailController::send(APP_NAME, $to, NO_REPLY_MAIL, 'Verify account', $contents);

        // Handle result
        if ($result) {
            // Log user out and redirect
            PageController::redirect('api/logout');
            AlertController::globalAlert('Success! Your profile has been updated! Please verify your new email address!', AlertType::SUCCESS, 4);
        } else {
            FormController::addAlert('An error occurred while sending your verification email! Please contact support.', AlertType::ERROR);
        }
    }

    /**
     * Handles API requests for profile image operations.
     *
     * @param Page $page Page object with URL parameters
     *
     * @return void
     */
    final public function api(Page $page): void
    {
        // Route to appropriate image operation
        if (isset($page->urlArr['subpages'][0])) switch ($page->urlArr['subpages'][0]) {
            case 'update-profile-image':
                self::updateProfileImage();
                break;
            case 'delete-profile-image':
                self::deleteProfileImage();
                break;
        }
    }

    /**
     * Handles profile image upload and validation.
     *
     * @return void
     */
    private static function updateProfileImage(): void
    {
        // Check if file was uploaded successfully
        if (!isset($_FILES['new_img']) || $_FILES['new_img']['error'] !== UPLOAD_ERR_OK) {
            PageController::redirect('profile');
            AlertController::globalAlert('Image upload failed. Please try again.', AlertType::ERROR, 4);
            return;
        }

        $file = $_FILES['new_img'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        // Verify mime type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        // Validate image mime type
        if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            PageController::redirect('profile');
            AlertController::globalAlert('The uploaded file is not a valid image type.', AlertType::ERROR, 4);
            return;
        }

        // Validate image using getimagesize
        if (getimagesize($file['tmp_name']) === false) {
            PageController::redirect('profile');
            AlertController::globalAlert('The uploaded file is not a valid image.', AlertType::ERROR, 4);
            return;
        }

        // Check file size (max 2MB)
        if ($file['size'] > 2 * 1024 * 1024) {
            PageController::redirect('profile');
            AlertController::globalAlert('The image size is too large. Please choose an image that is less than 2MB.', AlertType::ERROR, 4);
            return;
        }

        $id = SessionController::get('user')['id'];
        $path = $_SERVER['DOCUMENT_ROOT'] . '/img/profile/';

        // Get old profile image
        $old = DB::single(
            'profile_img',
            'users',
            compact('id')
        )['profile_img'] ?? null;

        // Delete old image if exists
        if ($old && is_file($path . $old)) unlink($path . $old);

        // Generate new filename
        $name = "{$id}_" . time() . ".$extension";

        // Move uploaded file
        move_uploaded_file($file['tmp_name'], $path . $name);

        // Update database
        DB::update(
            'users',
            [
                'profile_img' => $name
            ],
            compact('id')
        );

        // Redirect with success message
        PageController::redirect('profile');
        AlertController::globalAlert('Profile image updated successfully!', AlertType::SUCCESS, 4);
    }

    /**
     * Deletes user's profile image from filesystem and database.
     *
     * @return void
     */
    private static function deleteProfileImage(): void
    {
        $id = SessionController::get('user')['id'];

        // Get current profile image
        $old = DB::single(
            'profile_img',
            'users',
            compact('id')
        )['profile_img'] ?? null;

        // Delete image file if exists
        if ($old && is_file($_SERVER['DOCUMENT_ROOT'] . '/img/profile/' . $old)) {
            unlink($_SERVER['DOCUMENT_ROOT'] . '/img/profile/' . $old);
        }

        // Remove from database
        DB::update(
            'users',
            [
                'profile_img' => null
            ],
            compact('id')
        );

        // Redirect with success message
        PageController::redirect('profile');
        AlertController::globalAlert('Profile image deleted successfully!', AlertType::SUCCESS, 4);
    }
}
