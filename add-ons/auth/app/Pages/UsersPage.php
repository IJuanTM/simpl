<?php

declare(strict_types=1);

namespace app\Pages;

use app\Controllers\AlertController;
use app\Controllers\AuthController;
use app\Controllers\FormController;
use app\Controllers\PageController;
use app\Controllers\SessionController;
use app\Database\DB;
use app\Enums\AlertType;
use app\Models\Page;

/**
 * UsersPage
 *
 * Administrative user management including create/edit/delete/restore and
 * password generation. Requires admin role to access.
 */
class UsersPage
{
    public int $page = 0;
    public array $user;
    public array $users;
    public string $generatedPassword = '';

    public function __construct(Page $page)
    {
        // Require admin authentication (role ID 1)
        AuthController::requireAuth([1]);

        // Get pagination page number
        if (isset($page->urlArr['params']['page'])) $this->page = (int)$page->urlArr['params']['page'];

        // Get all users from database
        $this->users = DB::select(
            '*',
            'users'
        );

        // Get role for each user
        foreach ($this->users as $key => $user) {
            $this->users[$key]['role'] = DB::single(
                'role_id',
                'user_roles',
                [
                    'user_id' => $user['id']
                ]
            )['role_id'];
        }

        // Handle specific actions
        if (isset($page->urlArr['subpages'][0])) {
            // Generate password for user creation
            if ($page->urlArr['subpages'][0] === 'create') $this->generatedPassword = AuthController::generatePassword();

            // Load user data for edit/delete/restore actions
            if (in_array($page->urlArr['subpages'][0], ['edit', 'delete', 'restore'])) {
                // Validate user ID parameter
                if (!isset($page->urlArr['params']['id'])) {
                    PageController::redirect('users', 2);
                    return;
                }

                // Find user in users array
                $index = array_search((int)$page->urlArr['params']['id'], array_column($this->users, 'id'), true);

                // Check if user exists
                if ($index === false) {
                    PageController::redirect('users', 2);
                    return;
                }

                $this->user = $this->users[$index];

                // Prevent admin from modifying their own account
                if ($this->user['id'] === SessionController::get('user')['id']) PageController::redirect('users', 2);
            }

            // Process form submission
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) $this->post($page);
        }
    }

    /**
     * Processes user management form submissions.
     *
     * @param Page $page Page object with URL parameters
     *
     * @return void
     */
    private function post(Page $page): void
    {
        // Handle user creation
        if ($page->urlArr['subpages'][0] === 'create') {
            // Validate form fields
            if (
                !FormController::validate('username', ['maxLength' => 100]) ||
                !FormController::validate('first_name', ['maxLength' => 100]) ||
                !FormController::validate('infix', ['maxLength' => 50]) ||
                !FormController::validate('last_name', ['maxLength' => 100]) ||
                !FormController::validate('email', ['required', 'maxLength' => 100, 'type' => 'email']) ||
                !FormController::validate('role', ['required', 'type' => 'number'])
            ) return;

            // Sanitize inputs
            FormController::sanitizeFields(['username', 'first_name', 'infix', 'last_name', 'email']);

            // Check if email already exists
            if (SessionController::get('user')['email'] !== $_POST['email'] && AuthController::checkEmail($_POST['email'])) {
                $_POST['email'] = '';
                FormController::addAlert('An account with this email already exists!', AlertType::WARNING);
                return;
            }

            // Create user
            self::create(
                $_POST['email'],
                $this->generatedPassword,
                $_POST['role'],
                $_POST['username'],
                $_POST['first_name'],
                $_POST['infix'],
                $_POST['last_name']
            );
        }

        // Handle user editing
        if ($page->urlArr['subpages'][0] === 'edit') {
            // Validate form fields
            if (
                !FormController::validate('username', ['maxLength' => 100]) ||
                !FormController::validate('first_name', ['maxLength' => 100]) ||
                !FormController::validate('infix', ['maxLength' => 50]) ||
                !FormController::validate('last_name', ['maxLength' => 100]) ||
                !FormController::validate('email', ['required', 'maxLength' => 100, 'type' => 'email']) ||
                !FormController::validate('role', ['required', 'type' => 'number'])
            ) return;

            // Sanitize inputs
            FormController::sanitizeFields(['username', 'first_name', 'infix', 'last_name', 'email']);

            // Check if email already in use by another user
            if (SessionController::get('user')['email'] !== $_POST['email'] && AuthController::checkEmail($_POST['email'])) {
                $_POST['email'] = SessionController::get('user')['email'];
                FormController::addAlert('An account with this email already exists!', AlertType::WARNING);
                return;
            }

            // Update user
            self::update(
                $_POST['id'],
                $_POST['username'],
                $_POST['first_name'],
                $_POST['infix'],
                $_POST['last_name'],
                $_POST['email'],
                $_POST['role']
            );
        }

        // Handle user deletion
        if ($page->urlArr['subpages'][0] === 'delete') $this->delete($_POST['id']);

        // Handle user restoration
        if ($page->urlArr['subpages'][0] === 'restore') {
            // Only restore if user is actually inactive
            if (isset($this->user) && !$this->user['is_active']) $this->restore($_POST['id']);
            else PageController::redirect('users', 2);
        }
    }

    /**
     * Creates new user account with generated password.
     *
     * @param string $email Email address
     * @param string $rawPassword Temporary password (will be hashed)
     * @param int $role Role ID
     * @param string|null $username Username
     * @param string|null $first_name First name
     * @param string|null $infix Name infix
     * @param string|null $last_name Last name
     *
     * @return void
     */
    private static function create(string $email, string $rawPassword, int $role, string|null $username = null, string|null $first_name = null, string|null $infix = null, string|null $last_name = null): void
    {
        // Insert new user
        DB::insert(
            'users',
            [
                'username' => $username ?? null,
                'first_name' => $first_name ?? null,
                'infix' => $infix ?? null,
                'last_name' => $last_name ?? null,
                'email' => $email,
                'password' => password_hash($rawPassword, PASSWORD_HASH_ALGO, PASSWORD_HASH_OPTIONS),
                'must_change_password' => 1
            ]
        );

        // Get new user ID
        $id = AuthController::getUserIdByEmail($email);

        // Assign role
        DB::insert(
            'user_roles',
            [
                'user_id' => $id,
                'role_id' => $role
            ]
        );

        // Send account creation email
        AuthController::sendCreatedUserMail($email, $rawPassword);

        // Redirect with success message
        PageController::redirect('users');
        AlertController::globalAlert('Success! The user has been created!', AlertType::SUCCESS, 4);
    }

    /**
     * Updates existing user profile and role.
     *
     * @param int $id User ID
     * @param string|null $username Username
     * @param string|null $first_name First name
     * @param string|null $infix Name infix
     * @param string|null $last_name Last name
     * @param string $email Email address
     * @param int $role Role ID
     *
     * @return void
     */
    private static function update(int $id, string|null $username, string|null $first_name, string|null $infix, string|null $last_name, string $email, int $role): void
    {
        // Update user profile
        DB::update(
            'users',
            [
                'username' => $username ?? null,
                'first_name' => $first_name ?? null,
                'infix' => $infix ?? null,
                'last_name' => $last_name ?? null,
                'email' => $email
            ],
            compact('id')
        );

        // Update user role
        DB::update(
            'user_roles',
            [
                'role_id' => $role
            ],
            [
                'user_id' => $id
            ]
        );

        // Redirect with success message
        PageController::redirect('users');
        AlertController::globalAlert('Success! The user has been updated!', AlertType::SUCCESS, 4);
    }

    /**
     * Soft-deletes user account (sets inactive flag and deletion timestamp).
     *
     * @param int $id User ID
     *
     * @return void
     */
    private function delete(int $id): void
    {
        // Soft delete user
        DB::update(
            'users',
            [
                'is_active' => 0,
                'deleted_at' => date('Y-m-d H:i:s')
            ],
            compact('id')
        );

        // Redirect with success message
        PageController::redirect('users');
        AlertController::globalAlert('User successfully deleted!', AlertType::SUCCESS, 4);
    }

    /**
     * Restores previously deleted user account.
     *
     * @param int $id User ID
     *
     * @return void
     */
    private function restore(int $id): void
    {
        // Restore user
        DB::update(
            'users',
            [
                'is_active' => 1,
                'deleted_at' => null
            ],
            compact('id')
        );

        // Redirect with success message
        PageController::redirect('users');
        AlertController::globalAlert('User successfully restored!', AlertType::SUCCESS, 4);
    }
}
