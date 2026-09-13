<?php

declare(strict_types=1);

namespace app\Pages;

use app\Controllers\AppController;
use app\Controllers\AuthController;
use app\Controllers\BreadcrumbController;
use app\Controllers\PageController;
use app\Controllers\SessionController;
use app\Database\DB;
use app\Enums\AlertType;
use app\Enums\ErrorCode;
use app\Enums\Role;
use app\Models\Page;
use app\Pages\User\Settings;
use JsonException;

/**
 * Read-only profile view for a user identified by /user/{id}, or the settings area under /user/settings.
 * Visitors see the username and profile image; the owner also gets links into their settings; admins get an extended read-only view.
 */
class UserPage
{
    public array $user;
    public ?string $profileImage = null;
    public bool $isOwner = false;
    public bool $isAdmin = false;
    public ?Settings $settings = null;

    public function __construct(Page $page)
    {
        if ($page->subpage() === 'settings') {
            $this->settings = new Settings($page);
            return;
        }

        $this->loadUser($page);
    }

    /**
     * Loads and sanitizes the target user and resolves owner/admin viewing rights.
     *
     * @param Page $page
     *
     * @return void
     */
    private function loadUser(Page $page): void
    {
        $id = (int)AppController::sanitize($page->subpage() ?? '');

        if (empty($id)) {
            PageController::error(ErrorCode::NOT_FOUND);
            exit;
        }

        $user = AuthController::getUserWithRole($id);

        if (!$user) {
            PageController::error(ErrorCode::NOT_FOUND);
            exit;
        }

        $user['role'] = isset($user['role']) ? Role::tryFrom($user['role']) : null;
        $user['is_verified'] = AuthController::isVerified($user['id']);

        foreach (['username', 'email', 'first_name', 'last_name', 'last_login', 'created_at'] as $field) {
            if ($user[$field] !== null) $user[$field] = AppController::sanitize($user[$field]);
        }
        if ($user['status'] !== null) $user['status'] = AppController::sanitize((string)$user['status']);
        $this->user = $user;
        $this->profileImage = AuthController::getProfileImage($id);

        $currentUser = SessionController::get('user');
        $this->isOwner = $currentUser !== null && (int)$currentUser['id'] === $id;
        $this->isAdmin = $currentUser !== null && $currentUser['role'] === Role::ADMIN->value;

        BreadcrumbController::set([['label' => $this->isOwner ? 'Profile' : 'User', 'url' => null]]);
    }

    /**
     * Routes settings API calls to the settings delegate, and profile-image calls to the handlers below.
     *
     * @param Page $page Page object with URL parameters
     *
     * @return void
     * @throws JsonException
     */
    final public function api(Page $page): void
    {
        if ($this->settings !== null) {
            $this->settings->api($page);
            return;
        }

        // Profile image actions change state and act on the logged-in user; require authentication.
        AuthController::requireAuth();

        // State-changing only: a GET would run these without passing PageController's CSRF check.
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            PageController::error(ErrorCode::METHOD_NOT_ALLOWED);
            return;
        }

        match ($page->subpage(1)) {
            'update-profile-image' => self::updateProfileImage(),
            'delete-profile-image' => self::deleteProfileImage(),
            default => PageController::error(ErrorCode::NOT_FOUND),
        };
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

        // Checked before finfo/getimagesize inspect the file, so an oversized upload is rejected without spending work parsing it.
        if ($file['size'] > PROFILE_IMAGE_CONFIG['max_size_mb'] * 1024 * 1024) {
            self::uploadFailed('The image size is too large. Please choose an image that is less than ' . PROFILE_IMAGE_CONFIG['max_size_mb'] . 'MB.');
            return;
        }

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

        $dimensions = getimagesize($file['tmp_name']);

        if ($dimensions === false) {
            self::uploadFailed('The uploaded file is not a valid image.');
            return;
        }

        if ($dimensions[0] > PROFILE_IMAGE_CONFIG['max_dimension'] || $dimensions[1] > PROFILE_IMAGE_CONFIG['max_dimension']) {
            self::uploadFailed('The image dimensions are too large. Please choose an image no larger than ' . PROFILE_IMAGE_CONFIG['max_dimension'] . 'px on a side.');
            return;
        }

        $id = SessionController::get('user')['id'];
        $path = $_SERVER['DOCUMENT_ROOT'] . '/' . PROFILE_IMAGE_CONFIG['path'];
        $name = "{$id}_" . time() . ".$extension";

        if (!move_uploaded_file($file['tmp_name'], $path . $name)) {
            self::uploadFailed('Image upload failed. Please try again.');
            return;
        }

        // Remove the previous file only after the new one is confirmed written, so a failed upload can't orphan the DB reference.
        self::unlinkStoredProfileImage((int)$id);

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
     * Deletes the on-disk file backing the user's current profile_img, if one is set.
     */
    private static function unlinkStoredProfileImage(int $id): void
    {
        $old = DB::single(
            SELECT: 'profile_img',
            FROM: 'users',
            WHERE: compact('id')
        )['profile_img'] ?? null;

        $path = $_SERVER['DOCUMENT_ROOT'] . '/' . PROFILE_IMAGE_CONFIG['path'];
        if ($old && is_file($path . $old)) unlink($path . $old);
    }

    /**
     * Deletes user's profile image from filesystem and database.
     *
     * @return void
     */
    private static function deleteProfileImage(): void
    {
        $id = (int)SessionController::get('user')['id'];

        self::unlinkStoredProfileImage($id);

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
