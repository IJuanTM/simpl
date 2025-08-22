<?php

namespace app\Pages;

use app\Controllers\AlertController;
use app\Controllers\AuthController;
use app\Controllers\FormController;
use app\Controllers\PageController;
use app\Controllers\SessionController;
use app\Database\Database;
use app\Enums\AlertType;
use app\Models\Page;

/**
 * The UsersPage class is the controller for the users page.
 * It checks if the user is an admin when accessing this page.
 * It shows all the users in the database and allows the admin to edit, delete or restore a user.
 */
class UsersPage
{
    public int $page = 0;
    public array $user;
    public array $users;

    public function __construct(Page $page)
    {
        // Only allow admins to access this page
        AuthController::access([1]);

        // Get the page number from the url
        if (isset($page->urlArr['params']['page'])) $this->page = (int)$page->urlArr['params']['page'];

        // Get all users from the database
        $db = new Database();
        $db->query('SELECT * FROM users');

        // Store the users in an array
        $this->users = $db->fetchAll();

        // Get the user roles for each user
        foreach ($this->users as $key => $user) {
            $db->query('SELECT role_id FROM user_roles WHERE user_id = :id');
            $db->bind(':id', $user['id']);

            // Store the user role in the users array
            $this->users[$key]['role'] = $db->single()['role_id'];
        }

        // Check if the user wants to perform a specific action
        if (isset($page->urlArr['subpages'][0])) {
            // Check if the user wants to edit a user, delete a user or restore a user
            if (in_array($page->urlArr['subpages'][0], ['edit', 'delete', 'restore'])) {
                // Check if the user id is not given in the url
                if (!isset($page->urlArr['params']['id'])) {
                    PageController::redirect('users', 2);
                    return;
                }

                // Get the index of the user in the users array
                $index = array_search((int)$page->urlArr['params']['id'], array_column($this->users, 'id'), true);

                // Check if the user exists in the users array
                if ($index === false) {
                    PageController::redirect('users', 2);
                    return;
                }

                // Store the user in a variable
                $this->user = $this->users[$index];

                // Check if the user is the same as the logged-in user
                if ($this->user['id'] === SessionController::get('user')['id']) PageController::redirect('users', 2);
            }

            // Check if a form is submitted
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) $this->post($page);
        }
    }

    /**
     * This method is for handling the POST request of the users page.
     *
     * @param object $page
     */
    private function post(object $page): void
    {
        // Check if the user wants to edit a user and if the form is submitted
        if ($page->urlArr['subpages'][0] === 'edit') {
            // Check if the email field is entered
            if (empty($_POST['email'])) {
                FormController::addAlert('Please enter a new email!', AlertType::WARNING);
                PageController::redirect('users/edit/' . $_POST['id']);
                return;
            }

            // Check if the email field is not too long
            if (strlen($_POST['email']) > 100) {
                FormController::addAlert('The email is too long!', AlertType::WARNING);
                PageController::redirect('users/edit/' . $_POST['id']);
                return;
            }

            // Check if the email is valid
            if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
                FormController::addAlert('Please enter a valid email!', AlertType::WARNING);
                PageController::redirect('users/edit/' . $_POST['id']);
                return;
            }

            // Update the user
            self::update($_POST['id'], FormController::sanitize($_POST['username']), FormController::sanitize($_POST['email']), $_POST['role']);
        }

        // Check if the user wants to delete a user and if the form is submitted
        if ($page->urlArr['subpages'][0] === 'delete') $this->delete($_POST['id']);

        // Check if the user wants to restore a user
        if ($page->urlArr['subpages'][0] === 'restore') {
            // Check if the user is deleted or not
            if (isset($this->user) && !$this->user['is_active']) $this->restore($_POST['id']);
            else PageController::redirect('users', 2);
        }
    }

    /**
     * This method is for updating a user's profile.
     * An administrator can update the user's name, email and role.
     *
     * @param int $id
     * @param string $username
     * @param string $email
     * @param int $role
     */
    public static function update(int $id, string $username, string $email, int $role): void
    {
        $db = new Database();

        // Get the user
        $db->query('SELECT * FROM users WHERE id = :id');
        $db->bind(':id', $id);
        $user = $db->single();

        // Check if the username has changed
        if ($user['username'] !== $username) {
            // Update the username in the database
            $db->query('UPDATE users SET username = :username WHERE id = :id');
            $db->bind(':username', $username);
            $db->bind(':id', $id);
            $db->execute();
        }

        // Check if the email has changed
        if ($user['email'] !== $email) {
            // Check if the email is already in use
            if (AuthController::checkEmail($email)) {
                $_POST['email'] = $user['email'];

                FormController::addAlert('An account with this email already exists!', 'warning');
                PageController::redirect("users/edit/$id");
                return;
            }

            // Update the email in the database
            $db->query('UPDATE users SET email = :email WHERE id = :id');
            $db->bind(':email', $email);
            $db->bind(':id', $id);
            $db->execute();
        }

        // Get the user role from the database
        $db->query('SELECT role_id FROM user_roles WHERE user_id = :id');
        $db->bind(':id', $id);

        // Check if the user role has changed
        if ($db->single()['role_id'] !== $role) {
            // Update the user role in the database
            $db->query('UPDATE user_roles SET role_id = :role WHERE user_id = :id');
            $db->bind(':role', $role);
            $db->bind(':id', $id);
            $db->execute();
        }

        // Redirect to the users page with a success message
        PageController::redirect('users');
        AlertController::alert('Success! The user has been updated!', AlertType::SUCCESS, 4);
    }

    /**
     * This method is for deleting the user in the database.
     *
     * @param int $id
     */
    private function delete(int $id): void
    {
        $db = new Database();

        // Delete the user in the database
        $db->query('UPDATE users SET is_active = 0, deleted_at = NOW() WHERE id = :id');
        $db->bind(':id', $id);
        $db->execute();

        // Redirect to the users page with a success message
        PageController::redirect('users');
        AlertController::alert('User successfully deleted!', AlertType::SUCCESS, 4);
    }

    /**
     * This method is for restoring the user after it has been deleted.
     *
     * @param int $id
     */
    private function restore(int $id): void
    {
        $db = new Database();

        // Restore the user in the database
        $db->query('UPDATE users SET is_active = 1, deleted_at = NULL WHERE id = :id');
        $db->bind(':id', $id);
        $db->execute();

        // Redirect to the users page with a success message
        PageController::redirect('users');
        AlertController::alert('User successfully restored!', AlertType::SUCCESS, 4);
    }
}
