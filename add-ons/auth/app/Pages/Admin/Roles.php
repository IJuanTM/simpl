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
use app\Models\Url;
use app\Pages\Admin\Traits\AdminTableTrait;

/**
 * Roles (admin)
 *
 * Handles role listing, creation, editing, and deletion for the admin panel.
 * Delete is POST-only via a modal confirmation dialog. Uses AdminTableTrait
 * for column rendering only - no search, filters, sort or pagination.
 */
class Roles
{
    use AdminTableTrait;

    public ?string $subAction;
    public array $roles = [];
    public array $role = [];

    public function __construct(Page $page)
    {
        $this->subAction = $page->subpage(1);
        $this->tableColumns = self::getTableColumns();
        $this->itemLabel = 'roles';
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
     * Returns the table column definitions for the roles table.
     *
     * @return array<int, array{key: string, label: string, sortable: bool, width: int|null, visible: bool}>
     */
    private static function getTableColumns(): array
    {
        return array_map(static fn(array $c): array => ['key' => $c[0], 'label' => $c[1], 'sortable' => $c[2], 'width' => $c[3], 'visible' => $c[4]], [
            ['id', 'Id', false, 64, true],
            ['name', 'Name', false, 256, true],
            ['user_count', 'Users', false, 96, true],
            ['actions', 'Actions', false, null, true],
        ]);
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
        if (!FormController::validate('name', ['required', 'maxLength' => MAX_ROLE_NAME_LENGTH])) return;

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
        if (!FormController::validate('name', ['required', 'maxLength' => MAX_ROLE_NAME_LENGTH])) return;

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
            FormController::addAlert("Cannot delete this role - $userCount user" . ($userCount !== 1 ? 's are' : ' is') . " assigned to it. Reassign them first.", AlertType::WARNING);
            return;
        }

        DB::delete(
            FROM: 'roles',
            WHERE: compact('id')
        );

        PageController::redirect('admin/roles');
        AlertController::globalAlert('Role deleted successfully!', AlertType::SUCCESS, 4);
    }

    /**
     * Renders a role row's cell for the given column.
     */
    public function renderCell(array $column, array $row): string
    {
        return match ($column['key']) {
            'id' => (string)$row['id'],
            'name' => $row['name'],
            'user_count' => '<a class="link" href="' . Url::to('admin/users?' . http_build_query(['role' => $row['name']])) . '">' . $row['user_count'] . '</a>',
            default => '',
        };
    }

    /**
     * Row data for the current page.
     */
    private function pageRows(): array
    {
        return $this->roles;
    }

    /**
     * Overrides the trait default to add the edit/delete actions.
     */
    private function renderActionsCell(array $row): string
    {
        return '<td class="table-actions"><div class="row g-col-0.5 center-y">'
            . '<a class="col table-action f-0" href="/admin/roles/edit?id=' . $row['id'] . '"><i class="fas fa-pen"></i></a>'
            . '<button class="col table-action delete f-0" type="button" data-cooldown="300" data-modal-role-delete data-role-id="' . $row['id'] . '" data-role-name="' . $row['name'] . '" data-role-user-count="' . $row['user_count'] . '"><i class="fas fa-trash"></i></button>'
            . '</div></td>';
    }

    /**
     * Base route for pagination/sort-link hrefs.
     */
    private function routePath(): string
    {
        return '/admin/roles';
    }
}
