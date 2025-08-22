<?php

namespace app\Pages;

use app\Controllers\AlertController;
use app\Controllers\AuthController;
use app\Controllers\FormController;
use app\Controllers\MailController;
use app\Controllers\PageController;
use app\Controllers\SessionController;
use app\Database\Database;
use app\Enums\AlertType;
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
            PageController::redirect('error/403');
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
        $valid = true;

        // Validate the form fields
        if (!FormController::validate('username', ['maxLength' => 100])) $valid = false;
        if (!FormController::validate('email', ['required', 'maxLength' => 100, 'type' => 'email'])) $valid = false;

        if (!$valid) return;

        // Sanitize the email
        $_POST['email'] = FormController::sanitize($_POST['email']);

        // Check if the email is changed and if it is already in use by another user
        if (SessionController::get('user')['email'] !== $_POST['email'] && AuthController::checkEmail($_POST['email'])) {
            $_POST['email'] = SessionController::get('user')['email'];

            FormController::addAlert('An account with this email already exists!', AlertType::WARNING);
            return;
        }

        // Update the user
        $this->update(SessionController::get('user')['id'], FormController::sanitize($_POST['username']), $_POST['email']);
    }

    /**
     * Updates user profile information in database.
     *
     * @param int $id User ID
     * @param string $username New username
     * @param string $email New email address
     */
    private function update(int $id, string $username, string $email): void
    {
        $db = new Database();

        // Check if the username has changed
        if (SessionController::get('user')['username'] !== $username) {
            // Update the username in the database
            $db->query('UPDATE users SET username = :username WHERE id = :id');
            $db->bind(':username', $username);
            $db->bind(':id', $id);
            $db->execute();
        }

        // Check if the email has changed
        if (SessionController::get('user')['email'] !== $email) {
            // Check if the email is already in use
            if (AuthController::checkEmail($email)) {
                FormController::addAlert('An account with this email already exists!', AlertType::WARNING);
                PageController::redirect("users/edit/$id");
                return;
            }

            // Update the email in the database
            $db->query('UPDATE users SET email = :email WHERE id = :id');
            $db->bind(':email', $email);
            $db->bind(':id', $id);
            $db->execute();

            // Generate a verification token
            $token = AuthController::generateToken(4);

            // Set the verification token in the database
            $db->query('INSERT INTO tokens (user_id, token, type) VALUES(:id, :token, :type)');
            $db->bind(':id', $id);
            $db->bind(':token', $token);
            $db->bind(':type', 'verification');
            $db->execute();

            // Send a verification email to the user
            $this->verificationMail($id, $email, $token);
            return;
        }

        // Get the updated user from the database
        $db->query('SELECT * FROM users WHERE id = :id');
        $db->bind(':id', $id);
        $user = $db->single();

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
            'code' => $code,
            'link' => Url::to("verify-account/$id/$code")
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
     * @param object $page Page object with request information
     */
    final public function api(object $page): void
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

        $db = new Database();

        // Fetch the old image name from the database
        $db->query('SELECT profile_img FROM users WHERE id = :id');
        $db->bind(':id', $id);
        $old = $db->single()['profile_img'] ?? null;

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
        $db->query('UPDATE users SET profile_img = :profile_img WHERE id = :id');
        $db->bind(':profile_img', $name);
        $db->bind(':id', $id);
        $db->execute();

        // Redirect to the profile page with a success message
        PageController::redirect('profile');
        AlertController::alert('Profile image updated successfully!', AlertType::SUCCESS, 4);
    }

    /**
     * Removes user's profile image.
     */
    private static function deleteProfileImage(): void
    {
        $db = new Database();

        // Fetch the old image name from the database
        $db->query('SELECT profile_img FROM users WHERE id = :id');
        $db->bind(':id', SessionController::get('user')['id']);
        $old = $db->single()['profile_img'] ?? null;

        // Remove the old image if it exists
        if ($old) {
            $oldPath = $_SERVER['DOCUMENT_ROOT'] . '/img/profile/' . $old;

            // Remove the image if it exists
            if (is_file($oldPath)) unlink($oldPath);
        }

        // Remove the profile image from the database
        $db->query('UPDATE users SET profile_img = NULL WHERE id = :id');
        $db->bind(':id', SessionController::get('user')['id']);
        $db->execute();

        // Redirect to the profile page with a success message
        PageController::redirect('profile');
        AlertController::alert('Profile image deleted successfully!', AlertType::SUCCESS, 4);
    }
}
