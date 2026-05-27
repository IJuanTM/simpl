<?php

declare(strict_types=1);

namespace app\Pages\Admin;

use app\Controllers\AlertController;
use app\Controllers\AppController;
use app\Controllers\FormController;
use app\Controllers\PageController;
use app\Database\DB;
use app\Enums\AlertType;
use app\Models\Page;

/**
 * Roles (admin)
 *
 * Handles role listing, creation, editing, and deletion for the admin panel.
 * Delete is POST-only via a modal confirmation dialog.
 */
class Roles
{
    public ?string $subAction;
    public array $roles = [];
    public array $role = [];

    public function __construct(Page $page)
    {
        $this->subAction = $page->subpage(1);
        $this->loadRoles();

        if (in_array($this->subAction, ['edit', 'delete'])) {
            if (!$page->param('id')) {
                PageController::redirect('admin/roles', 2);
                return;
            }

            $id = (int)$page->param('id');
            $role = DB::single(
                SELECT: '*',
                FROM: 'roles',
                WHERE: compact('id')
            );

            if (!$role) {
                PageController::redirect('admin/roles', 2);
                return;
            }

            $role['name'] = AppController::sanitize($role['name']);
            $this->role = $role;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) $this->post();
    }

    /**
     * Loads all roles with their assigned user counts.
     *
     * @return void
     */
    private function loadRoles(): void
    {
        $this->roles = DB::select(
            SELECT: ['roles.id', 'roles.name', 'COUNT(user_roles.user_id) AS user_count'],
            FROM: 'roles',
            JOIN: [
                'id', ['user_roles', 'role_id']
            ],
            GROUP_BY: 'roles.id',
            ORDER_BY: 'roles.name ASC'
        );

        foreach ($this->roles as $key => $role) $this->roles[$key]['name'] = AppController::sanitize($role['name']);
    }

    /**
     * Dispatches the POST request to the appropriate action handler.
     *
     * @return void
     */
    private function post(): void
    {
        match ($this->subAction) {
            'create' => $this->createRole(),
            'edit' => $this->updateRole($this->role['id']),
            'delete' => $this->deleteRole($this->role['id']),
            default => null,
        };
    }

    /**
     * Validates and inserts a new role.
     *
     * @return void
     */
    private function createRole(): void
    {
        if (!FormController::validate('name', ['required', 'maxLength' => 50])) return;

        if (DB::exists(
            FROM: 'roles',
            WHERE: [
                'name' => $_POST['name']
            ]
        )) {
            FormController::addAlert('A role with this name already exists!', AlertType::WARNING);
            return;
        }

        DB::insert(
            INTO: 'roles',
            VALUES: [
                'name' => $_POST['name']
            ]
        );

        PageController::redirect('admin/roles');
        AlertController::globalAlert('Role created successfully!', AlertType::SUCCESS, 4);
    }

    /**
     * Validates and updates an existing role's name.
     *
     * @param int $id Role ID to update
     *
     * @return void
     */
    private function updateRole(int $id): void
    {
        if (!FormController::validate('name', ['required', 'maxLength' => 50])) return;

        $existing = DB::single(
            SELECT: 'id',
            FROM: 'roles',
            WHERE: [
                'name' => $_POST['name']
            ]
        );

        if ($existing && (int)$existing['id'] !== $id) {
            FormController::addAlert('A role with this name already exists!', AlertType::WARNING);
            return;
        }

        DB::update(
            UPDATE: 'roles',
            SET: [
                'name' => $_POST['name']
            ],
            WHERE: compact('id')
        );

        PageController::redirect('admin/roles');
        AlertController::globalAlert('Role updated successfully!', AlertType::SUCCESS, 4);
    }

    /**
     * Deletes a role, rejecting the request if any users are still assigned to it.
     *
     * @param int $id Role ID to delete
     *
     * @return void
     */
    private function deleteRole(int $id): void
    {
        $userCount = DB::count(
            FROM: 'user_roles',
            WHERE: [
                'role_id' => $id
            ]
        );

        if ($userCount > 0) {
            FormController::addAlert("Cannot delete this role — $userCount user" . ($userCount !== 1 ? 's are' : ' is') . " assigned to it. Reassign them first.", AlertType::WARNING);
            return;
        }

        DB::delete(
            FROM: 'roles',
            WHERE: compact('id')
        );

        PageController::redirect('admin/roles');
        AlertController::globalAlert('Role deleted successfully!', AlertType::SUCCESS, 4);
    }
}
