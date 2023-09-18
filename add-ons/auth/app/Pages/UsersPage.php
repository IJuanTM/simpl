<?php

namespace app\Pages;

use app\Controllers\{AppController, FormController, PageController, UserController};
use app\Database\Database;
use app\Models\PageModel;

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

    public function __construct(PageModel $page)
    {
        // Only allow admins to access this page
        UserController::access([1]);

        // Get the page number from the url
        if (isset($page->getUrl()['params']['page'])) $this->page = (int)$page->getUrl()['params']['page'];

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
        if (isset($page->getUrl()['subpages'][0])) {
            // Check if the user wants to edit a user, delete a user or restore a user
            if (in_array($page->getUrl()['subpages'][0], ['edit', 'delete', 'restore'])) {
                // Check if the user id is given in the url
                if (isset($page->getUrl()['params']['id'])) {
                    // Get the index of the user in the users array
                    $index = array_search((int)$page->getUrl()['params']['id'], array_column($this->users, 'id'));

                    // Check if the user exists in the users array
                    if ($index === false) {
                        PageController::redirect('users', 2);
                        return;
                    }

                    // Store the user in a variable
                    $this->user = $this->users[$index];

                    // Check if the user is the same as the logged in user
                    if ($this->user['id'] == $_SESSION['user']['id']) PageController::redirect('users', 2);
                } else PageController::redirect('users', 2);
            }

            // Check if the user wants to edit a user and if the form is submitted
            if ($page->getUrl()['subpages'][0] == 'edit' && isset($_POST['submit'])) {
                // Check if the email field is entered
                if (empty($_POST['email'])) {
                    FormController::alert('Please enter a new email!', 'warning', 'users/edit/' . $_POST['id']);
                    return;
                }

                // Check if the email field is not too long
                if (strlen($_POST['email']) > 100) {
                    FormController::alert('The email is too long!', 'warning', 'users/edit/' . $_POST['id']);
                    return;
                }

                // Check if the email is valid
                if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
                    FormController::alert('Please enter a valid email!', 'warning', 'users/edit/' . $_POST['id']);
                    return;
                }

                // Update the user
                self::update($_POST['id'], AppController::sanitize($_POST['name']), AppController::sanitize($_POST['email']), $_POST['role']);
            }

            // Check if the user wants to delete a user and if the form is submitted
            if ($page->getUrl()['subpages'][0] == 'delete' && isset($_POST['submit'])) $this->delete($_POST['id']);

            // Check if the user wants to restore a user
            if ($page->getUrl()['subpages'][0] == 'restore' && isset($_POST['submit'])) {
                // Check if the user is deleted or not
                if (isset($this->user) && !$this->user['is_active']) $this->restore($_POST['id']);
                else PageController::redirect('users', 2);
            }
        }
    }

    /**
     * This method is for updating an user's profile.
     * An administrator can update the user's name, email and role.
     *
     * @param int $id
     * @param string $name
     * @param string $email
     * @param int $role
     *
     * @return void
     */
    public static function update(int $id, string $name, string $email, int $role): void
    {
        $db = new Database();

        // Get the user
        $db->query('SELECT * FROM users WHERE id = :id');
        $db->bind(':id', $id);
        $user = $db->single();

        // Check if the name has changed
        if ($user['name'] !== $name) {
            // Update the name in the database
            $db->query('UPDATE users SET name = :name WHERE id = :id');
            $db->bind(':name', $name);
            $db->bind(':id', $id);
            $db->execute();
        }

        // Check if the email has changed
        if ($user['email'] !== $email) {
            // Check if the email is already in use
            if (UserController::checkEmail($email)) {
                $_POST['email'] = $user['email'];
                FormController::alert('An account with this email already exists!', 'warning', "users/edit/$id");
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

        // Redirect to the users page
        PageController::redirect('users');

        // Show the success message
        AppController::alert('Success! The user has been updated!', ['success', 'global'], 4);
    }

    /**
     * This method is for deleting the user in the database.
     *
     * @param int $id
     *
     * @return void
     */
    private function delete(int $id): void
    {
        $db = new Database();

        // Delete the user in the database
        $db->query('UPDATE users SET is_active = 0, deleted_at = now() WHERE id = :id');
        $db->bind(':id', $id);
        $db->execute();

        // Redirect to the users page
        PageController::redirect('users');

        // Show the success message
        AppController::alert('User successfully deleted!', ['success', 'global'], 4);
    }

    /**
     * This method is for restoring the user after it has been deleted.
     *
     * @param int $id
     *
     * @return void
     */
    private function restore(int $id): void
    {
        $db = new Database();

        // Restore the user in the database
        $db->query('UPDATE users SET is_active = 1, deleted_at = NULL WHERE id = :id');
        $db->bind(':id', $id);
        $db->execute();

        // Redirect to the users page
        PageController::redirect('users');

        // Show the success message
        AppController::alert('User successfully restored!', ['success', 'global'], 4);
    }
}
