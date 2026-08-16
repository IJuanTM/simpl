<?php

declare(strict_types=1);

namespace app\Pages;

use app\Controllers\AppController;
use app\Controllers\AuthController;
use app\Controllers\FormController;
use app\Controllers\PageController;
use app\Controllers\SessionController;
use app\Database\DB;
use app\Enums\AlertType;
use app\Enums\ErrorCode;
use app\Enums\Role;
use app\Models\Page;

/**
 * UserPage
 *
 * Profile view for a user identified by /user/{id}. Visitors see only the
 * username and profile image. The profile owner sees an inline edit form.
 * Admins see a read-only extended view with an admin edit link.
 */
class UserPage
{
    public array $user;
    public string|null $profileImage = null;
    public bool $isOwner = false;
    public bool $isAdmin = false;

    public function __construct(Page $page)
    {
        $id = (int)AppController::sanitize($page->subpage() ?? '');

        if (empty($id)) {
            PageController::error(ErrorCode::NOT_FOUND);
            return;
        }

        // Load user with role in a single JOIN query.
        $user = AuthController::getUserWithRole($id);

        if (!$user) {
            PageController::error(ErrorCode::NOT_FOUND);
            return;
        }

        $user['role'] = isset($user['role']) ? Role::tryFrom($user['role']) : null;
        $user['is_verified'] = AuthController::isVerified($user['id']);

        if ($user['username'] !== null) $user['username'] = AppController::sanitize($user['username']);
        if ($user['email'] !== null) $user['email'] = AppController::sanitize($user['email']);
        if ($user['first_name'] !== null) $user['first_name'] = AppController::sanitize($user['first_name']);
        if ($user['last_name'] !== null) $user['last_name'] = AppController::sanitize($user['last_name']);
        if ($user['last_login'] !== null) $user['last_login'] = AppController::sanitize($user['last_login']);
        $this->user = $user;
        $this->profileImage = AuthController::getProfileImage($id);

        $currentUser = SessionController::get('user');
        $this->isOwner = $currentUser !== null && (int)$currentUser['id'] === $id;
        $this->isAdmin = $currentUser !== null && $currentUser['role'] === Role::ADMIN->value;

        if ($this->isOwner && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
            $this->updateProfile($id);
        }
    }

    /**
     * Validates and saves the profile fields submitted by the owner.
     */
    private function updateProfile(int $id): void
    {
        if (
            !FormController::validate('username', ['maxLength' => MAX_USERNAME_LENGTH]) ||
            !FormController::validate('first_name', ['maxLength' => MAX_NAME_LENGTH]) ||
            !FormController::validate('last_name', ['maxLength' => MAX_NAME_LENGTH]) ||
            !FormController::validate('email', ['required', 'maxLength' => MAX_EMAIL_LENGTH, 'type' => 'email'])
        ) return;

        if ($_POST['username'] !== '' && AuthController::usernameTakenByOtherUser($_POST['username'], $id)) {
            $_POST['username'] = $this->user['username'] ?? '';
            FormController::addAlert('That username is already taken!', AlertType::WARNING);
            return;
        }

        if (AuthController::emailTakenByOtherUser($_POST['email'], $id)) {
            $_POST['email'] = $this->user['email'];
            FormController::addAlert('An account with this email already exists!', AlertType::WARNING);
            return;
        }

        DB::update(
            UPDATE: 'users',
            SET: [
                'username' => $_POST['username'] ?: null,
                'first_name' => $_POST['first_name'] ?: null,
                'last_name' => $_POST['last_name'] ?: null,
                'email' => $_POST['email'],
            ],
            WHERE: compact('id')
        );

        PageController::redirectWithAlert('user/' . $id, 'Profile updated successfully!', AlertType::SUCCESS, 4);
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
        // Profile image actions change state and act on the logged-in user; require authentication.
        AuthController::requireAuth();

        if ($page->subpage(1) !== null) switch ($page->subpage(1)) {
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
        if (!isset($_FILES['new_img']) || $_FILES['new_img']['error'] !== UPLOAD_ERR_OK) {
            self::uploadFailed('Image upload failed. Please try again.');
            return;
        }

        $file = $_FILES['new_img'];

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, PROFILE_IMAGE_CONFIG['allowed_types'], true)) {
            self::uploadFailed('The uploaded file is not a valid image type.');
            return;
        }

        // Derive the extension from the validated mime type, never from the user-supplied filename.
        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => null
        };

        if ($extension === null) {
            self::uploadFailed('The uploaded file is not a valid image type.');
            return;
        }

        if (getimagesize($file['tmp_name']) === false) {
            self::uploadFailed('The uploaded file is not a valid image.');
            return;
        }

        if ($file['size'] > PROFILE_IMAGE_CONFIG['max_size_mb'] * 1024 * 1024) {
            self::uploadFailed('The image size is too large. Please choose an image that is less than ' . PROFILE_IMAGE_CONFIG['max_size_mb'] . 'MB.');
            return;
        }

        $id = SessionController::get('user')['id'];
        $path = $_SERVER['DOCUMENT_ROOT'] . '/' . PROFILE_IMAGE_CONFIG['path'];

        $old = DB::single(
            SELECT: 'profile_img',
            FROM: 'users',
            WHERE: compact('id')
        )['profile_img'] ?? null;

        if ($old && is_file($path . $old)) unlink($path . $old);

        $name = "{$id}_" . time() . ".$extension";

        if (!move_uploaded_file($file['tmp_name'], $path . $name)) {
            self::uploadFailed('Image upload failed. Please try again.');
            return;
        }

        DB::update(
            UPDATE: 'users',
            SET: [
                'profile_img' => $name
            ],
            WHERE: compact('id')
        );

        PageController::redirectWithAlert('profile', 'Profile image updated successfully!', AlertType::SUCCESS, 4);
    }

    /**
     * Redirects to the profile page with a generic upload-failure alert.
     *
     * @param string $message
     *
     * @return void
     */
    private static function uploadFailed(string $message): void
    {
        PageController::redirectWithAlert('profile', $message, AlertType::ERROR, 4);
    }

    /**
     * Deletes user's profile image from filesystem and database.
     *
     * @return void
     */
    private static function deleteProfileImage(): void
    {
        $id = SessionController::get('user')['id'];

        $old = DB::single(
            SELECT: 'profile_img',
            FROM: 'users',
            WHERE: compact('id')
        )['profile_img'] ?? null;

        if ($old && is_file($_SERVER['DOCUMENT_ROOT'] . '/' . PROFILE_IMAGE_CONFIG['path'] . $old)) unlink($_SERVER['DOCUMENT_ROOT'] . '/' . PROFILE_IMAGE_CONFIG['path'] . $old);

        DB::update(
            UPDATE: 'users',
            SET: [
                'profile_img' => null
            ],
            WHERE: compact('id')
        );

        PageController::redirectWithAlert('profile', 'Profile image deleted successfully!', AlertType::SUCCESS, 4);
    }
}
