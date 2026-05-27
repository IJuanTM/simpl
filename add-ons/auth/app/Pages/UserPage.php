<?php

declare(strict_types=1);

namespace app\Pages;

use app\Controllers\AlertController;
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
        // Get and sanitize user ID from URL
        $id = (int)AppController::sanitize($page->subpage() ?? '');

        if (empty($id)) {
            PageController::error(ErrorCode::NOT_FOUND);
            return;
        }

        // Load user from database
        $user = AuthController::getUserById($id);

        if (!$user) {
            PageController::error(ErrorCode::NOT_FOUND);
            return;
        }

        // Resolve role
        $roleId = DB::single(
            SELECT: 'role_id',
            FROM: 'user_roles',
            WHERE: [
                'user_id' => $user['id']
            ]
        )['role_id'] ?? null;

        $roleName = $roleId ? DB::single(
            SELECT: 'name',
            FROM: 'roles',
            WHERE: [
                'id' => $roleId
            ]
        )['name'] ?? null : null;

        $user['role'] = $roleName ? Role::tryFrom($roleName) : null;
        $user['is_verified'] = AuthController::isVerified($user['id']);

        if ($user['username'] !== null) $user['username'] = AppController::sanitize($user['username']);
        if ($user['email'] !== null) $user['email'] = AppController::sanitize($user['email']);
        if ($user['first_name'] !== null) $user['first_name'] = AppController::sanitize($user['first_name']);
        if ($user['last_name'] !== null) $user['last_name'] = AppController::sanitize($user['last_name']);
        if ($user['last_login'] !== null) $user['last_login'] = AppController::sanitize($user['last_login']);
        $this->user = $user;
        $this->profileImage = AuthController::getProfileImage($id);

        // Determine viewer relationship to this profile
        $currentUser = SessionController::get('user');
        $this->isOwner = $currentUser !== null && (int)$currentUser['id'] === $id;
        $this->isAdmin = $currentUser !== null && $currentUser['role'] === Role::ADMIN->value;

        // Handle profile update submitted by the owner
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

        // Reject the email if it belongs to a different account
        if (AuthController::checkEmail($_POST['email']) && AuthController::getUserIdByEmail($_POST['email']) !== $id) {
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

        PageController::redirect('user/' . $id);
        AlertController::globalAlert('Profile updated successfully!', AlertType::SUCCESS, 4);
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
        if (!in_array($mimeType, PROFILE_IMAGE_ALLOWED_TYPES, true)) {
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

        // Check file size
        if ($file['size'] > PROFILE_IMAGE_MAX_SIZE * 1024 * 1024) {
            PageController::redirect('profile');
            AlertController::globalAlert('The image size is too large. Please choose an image that is less than ' . PROFILE_IMAGE_MAX_SIZE . 'MB.', AlertType::ERROR, 4);
            return;
        }

        $id = SessionController::get('user')['id'];
        $path = $_SERVER['DOCUMENT_ROOT'] . '/' . PROFILE_IMAGE_PATH;

        // Get old profile image
        $old = DB::single(
            SELECT: 'profile_img',
            FROM: 'users',
            WHERE: compact('id')
        )['profile_img'] ?? null;

        // Delete old image if exists
        if ($old && is_file($path . $old)) unlink($path . $old);

        // Generate new filename
        $name = "{$id}_" . time() . ".$extension";

        // Move uploaded file
        move_uploaded_file($file['tmp_name'], $path . $name);

        // Update database
        DB::update(
            UPDATE: 'users',
            SET: [
                'profile_img' => $name
            ],
            WHERE: compact('id')
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
            SELECT: 'profile_img',
            FROM: 'users',
            WHERE: compact('id')
        )['profile_img'] ?? null;

        // Delete image file if exists
        if ($old && is_file($_SERVER['DOCUMENT_ROOT'] . '/' . PROFILE_IMAGE_PATH . $old)) {
            unlink($_SERVER['DOCUMENT_ROOT'] . '/' . PROFILE_IMAGE_PATH . $old);
        }

        // Remove from database
        DB::update(
            UPDATE: 'users',
            SET: [
                'profile_img' => null
            ],
            WHERE: compact('id')
        );

        // Redirect with success message
        PageController::redirect('profile');
        AlertController::globalAlert('Profile image deleted successfully!', AlertType::SUCCESS, 4);
    }
}
