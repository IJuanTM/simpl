<?php

declare(strict_types=1);

namespace app\Pages\Admin;

use app\Controllers\AlertController;
use app\Controllers\FormController;
use app\Controllers\PageController;
use app\Database\DB;
use app\Enums\AlertType;
use app\Models\Page;

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

            $this->role = $role;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
            $this->post();
            return;
        }

        // delete is POST-only (modal); redirect GET requests
        if ($this->subAction === 'delete') PageController::redirect('admin/roles');
    }

    private function loadRoles(): void
    {
        $this->roles = DB::select(
            SELECT: ['roles.id', 'roles.name', 'COUNT(user_roles.user_id) AS user_count'],
            FROM: 'roles',
            JOIN: ['id', ['user_roles', 'role_id']],
            GROUP_BY: 'roles.id',
            ORDER_BY: 'roles.name ASC'
        );
    }

    private function post(): void
    {
        match ($this->subAction) {
            'create' => $this->createRole(),
            'edit' => $this->updateRole($this->role['id']),
            'delete' => $this->deleteRole($this->role['id']),
            default => null,
        };
    }

    private function createRole(): void
    {
        if (!FormController::validate('name', ['required', 'maxLength' => 50])) return;
        FormController::sanitizeFields(['name']);

        if (DB::exists(FROM: 'roles', WHERE: ['name' => $_POST['name']])) {
            FormController::addAlert('A role with this name already exists!', AlertType::WARNING);
            return;
        }

        DB::insert(
            INTO: 'roles',
            VALUES: ['name' => $_POST['name']]
        );

        PageController::redirect('admin/roles');
        AlertController::globalAlert('Role created successfully!', AlertType::SUCCESS, 4);
    }

    private function updateRole(int $id): void
    {
        if (!FormController::validate('name', ['required', 'maxLength' => 50])) return;
        FormController::sanitizeFields(['name']);

        $existing = DB::single(SELECT: 'id', FROM: 'roles', WHERE: ['name' => $_POST['name']]);
        if ($existing && (int)$existing['id'] !== $id) {
            FormController::addAlert('A role with this name already exists!', AlertType::WARNING);
            return;
        }

        DB::update(
            UPDATE: 'roles',
            SET: ['name' => $_POST['name']],
            WHERE: compact('id')
        );

        PageController::redirect('admin/roles');
        AlertController::globalAlert('Role updated successfully!', AlertType::SUCCESS, 4);
    }

    private function deleteRole(int $id): void
    {
        $userCount = DB::count(
            FROM: 'user_roles',
            WHERE: ['role_id' => $id]
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
