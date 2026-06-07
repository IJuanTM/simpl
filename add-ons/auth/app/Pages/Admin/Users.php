<?php

declare(strict_types=1);

namespace app\Pages\Admin;

use app\Controllers\AlertController;
use app\Controllers\AppController;
use app\Controllers\AuthController;
use app\Controllers\FormController;
use app\Controllers\PageController;
use app\Controllers\SessionController;
use app\Database\DB;
use app\Enums\AlertType;
use app\Enums\Role;
use app\Models\Page;
use app\Pages\Admin\Traits\AdminTableTrait;
use JsonException;

/**
 * Users (admin)
 *
 * Handles user listing with search, filtering, sorting, and pagination, as well
 * as user creation, editing, soft-deletion, restoration, and permanent purge.
 * The table data is served via an API endpoint used by the front-end JS.
 */
class Users
{
    use AdminTableTrait;

    private const string SORT_ASC = 'asc';
    private const string SORT_DESC = 'desc';
    private const array PER_PAGE_OPTIONS = [10, 25, 50, 100];

    public ?string $subAction;
    public array $user = [];
    public array $allUsers = [];
    public array $users = [];
    public array $pagedUsers = [];
    public array $tableColumns = [];
    public array $visibleColumns = [];
    public string $generatedPassword = '';
    public int $perPage = 10;
    public array $perPageOptions = self::PER_PAGE_OPTIONS;
    public string $filterRole = '';
    public string $filterStatus = '';
    public string $filterVerified = '';
    public bool $hasActiveFilters = false;
    public ?string $sortColumn = 'id';
    public ?string $sortDirection = self::SORT_ASC;
    public int $totalUsers = 0;
    public int $totalAllUsers = 0;
    public int $currentUserId = 0;
    public array $activeSortParams = [];
    public array $activeFilterParams = [];
    public array $availableRoles = [];
    public string $searchDisplay = '';

    public function __construct(Page $page)
    {
        $this->subAction = $page->subpage(1);
        $this->availableRoles = DB::select(
            SELECT: '*',
            FROM: 'roles',
            ORDER_BY: 'name ASC'
        );

        foreach ($this->availableRoles as $key => $r) $this->availableRoles[$key]['name'] = AppController::sanitize($r['name']);

        $this->tableColumns = self::getTableColumns();
        $this->visibleColumns = $this->tableColumns;

        $this->readTableParams($page);

        if ($page->param('role') !== null) $this->filterRole = trim((string)$page->param('role'));
        if ($page->param('status') !== null && in_array($page->param('status'), ['active', 'inactive'], true)) $this->filterStatus = (string)$page->param('status');
        if ($page->param('verified') !== null && in_array($page->param('verified'), ['yes', 'no'], true)) $this->filterVerified = (string)$page->param('verified');

        $this->resolveSort($page->params);
        $this->loadUsers();
        $this->filterUsers();
        $this->sortUsers();
        $this->buildPagedUsers();
        $this->prepareViewData();
        $this->searchDisplay = AppController::sanitize($this->search);

        $subAction = $this->subAction;
        if ($subAction === null) return;

        if ($subAction === 'create') {
            $this->generatedPassword = AuthController::generatePassword();
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) $this->createUser();
            return;
        }

        if (in_array($subAction, ['edit', 'delete', 'restore', 'purge'])) {
            if (!$page->param('id')) {
                PageController::redirect('admin/users', 2);
                return;
            }

            $id = (int)$page->param('id');
            $index = array_search($id, array_column($this->allUsers, 'id'), true);

            if ($index === false) {
                PageController::redirect('admin/users', 2);
                return;
            }

            $this->user = $this->allUsers[$index];
            if ($this->user['username'] !== null) $this->user['username'] = AppController::sanitize($this->user['username']);
            if ($this->user['email'] !== null) $this->user['email'] = AppController::sanitize($this->user['email']);
            if ($this->user['first_name'] !== null) $this->user['first_name'] = AppController::sanitize($this->user['first_name']);
            if ($this->user['last_name'] !== null) $this->user['last_name'] = AppController::sanitize($this->user['last_name']);

            if ($this->user['id'] === SessionController::get('user')['id']) {
                PageController::redirect('admin/users', 2);
                return;
            }

            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) $this->post();
        }
    }

    /**
     * Returns the table column definitions for the users table.
     *
     * @return array<int, array{key: string, label: string, sortable: bool, width: int|null, visible: bool}>
     */
    private static function getTableColumns(): array
    {
        return array_map(static fn(array $c): array => ['key' => $c[0], 'label' => $c[1], 'sortable' => $c[2], 'width' => $c[3], 'visible' => $c[4]], [
            ['id', 'Id', true, null, true],
            ['username', 'Username', true, 128, true],
            ['email', 'Email', true, 192, true],
            ['first_name', 'First name', true, 128, true],
            ['last_name', 'Last name', true, 128, true],
            ['role', 'Role', true, 80, true],
            ['is_verified', 'Verified', true, 128, true],
            ['must_change_password', 'Has temporary password', true, 128, false],
            ['last_login', 'Last login', true, 160, true],
            ['created_at', 'Created at', true, 160, false],
            ['last_update', 'Last updated', true, 160, false],
            ['is_active', 'Is active', true, 128, true],
            ['deleted_at', 'Deleted at', true, 160, true],
            ['actions', 'Actions', false, null, true],
        ]);
    }

    /**
     * Reads and validates sort parameters from the request, falling back to defaults.
     *
     * @param array<string, mixed> $params URL query parameters
     *
     * @return void
     */
    private function resolveSort(array $params): void
    {
        $sortable = array_column(array_filter($this->tableColumns, static fn(array $c): bool => !empty($c['sortable'])), 'key');
        if (!isset($params['sort']) || !in_array($params['sort'], $sortable, true)) return;

        $this->sortColumn = $params['sort'];
        $dir = isset($params['dir']) ? strtolower((string)$params['dir']) : self::SORT_ASC;
        $this->sortDirection = in_array($dir, [self::SORT_ASC, self::SORT_DESC], true) ? $dir : self::SORT_ASC;
    }

    /**
     * Loads all users from the database and resolves their role and verification status.
     *
     * @return void
     */
    private function loadUsers(): void
    {
        $this->allUsers = DB::select(
            SELECT: [
                'users.*',
                'roles.name AS role_name',
                '(CASE WHEN EXISTS (SELECT 1 FROM tokens WHERE tokens.user_id = users.id AND tokens.type = \'verification\') THEN 0 ELSE 1 END) AS is_verified',
            ],
            FROM: 'users',
            JOIN: [
                ['id', ['user_roles', 'user_id']],
                [['user_roles', 'role_id'], ['roles', 'id']],
            ]
        );

        foreach ($this->allUsers as $key => $user) {
            $this->allUsers[$key]['role'] = isset($user['role_name']) ? Role::tryFrom($user['role_name']) : null;
            $this->allUsers[$key]['is_verified'] = (int)$user['is_verified'];
        }

        $this->users = $this->allUsers;
    }

    /**
     * Filters the user list by search query, role, status, and verification state.
     *
     * @return void
     */
    private function filterUsers(): void
    {
        if ($this->search !== '') {
            $needle = strtolower($this->search);
            $this->users = array_values(array_filter($this->users, static fn(array $user): bool => array_any([
                (string)$user['id'],
                (string)($user['username'] ?? ''),
                (string)($user['email'] ?? ''),
                (string)($user['first_name'] ?? ''),
                (string)($user['last_name'] ?? ''),
            ], static fn(string $v): bool => str_contains(strtolower($v), $needle))));
        }

        if ($this->filterRole !== '') {
            $role = $this->filterRole;
            $this->users = array_values(array_filter($this->users, static fn(array $user): bool => ($user['role_name'] ?? '') === $role));
        }

        if ($this->filterStatus !== '') {
            $active = $this->filterStatus === 'active' ? 1 : 0;
            $this->users = array_values(array_filter($this->users, static fn(array $user): bool => (int)$user['is_active'] === $active));
        }

        if ($this->filterVerified !== '') {
            $verified = $this->filterVerified === 'yes' ? 1 : 0;
            $this->users = array_values(array_filter($this->users, static fn(array $user): bool => (int)($user['is_verified'] ?? 0) === $verified));
        }
    }

    /**
     * Sorts the filtered user list by the active column and direction.
     *
     * @return void
     */
    private function sortUsers(): void
    {
        if (!$this->sortColumn || !$this->sortDirection) return;

        $col = $this->sortColumn;
        $desc = $this->sortDirection === self::SORT_DESC;

        usort($this->users, function (array $a, array $b) use ($col, $desc): int {
            $va = $this->getSortValue($a, $col);
            $vb = $this->getSortValue($b, $col);
            $result = is_int($va) && is_int($vb) ? $va <=> $vb : strcmp((string)$va, (string)$vb);
            return $desc ? -$result : $result;
        });
    }

    /**
     * Returns a normalized sort value for the given user and column.
     *
     * @param array<string, mixed> $user   User row
     * @param string               $column Column key
     *
     * @return int|string
     */
    private function getSortValue(array $user, string $column): int|string
    {
        return match ($column) {
            'id' => (int)$user['id'],
            'username' => strtolower((string)($user['username'] ?? '')),
            'email' => strtolower((string)$user['email']),
            'first_name' => strtolower((string)($user['first_name'] ?? '')),
            'last_name' => strtolower((string)($user['last_name'] ?? '')),
            'role' => strtolower((string)($user['role_name'] ?? '')),
            'is_verified' => (int)($user['is_verified'] ?? 0),
            'must_change_password' => (int)$user['must_change_password'],
            'last_login' => $user['last_login'] ? (int)strtotime((string)$user['last_login']) : 0,
            'created_at' => (int)strtotime((string)$user['created_at']),
            'last_update' => (int)strtotime((string)$user['last_update']),
            'is_active' => (int)$user['is_active'],
            'deleted_at' => $user['deleted_at'] ? (int)strtotime((string)$user['deleted_at']) : 0,
            default => 0,
        };
    }

    /**
     * Slices the sorted user list into the current page and sanitizes display fields.
     *
     * @return void
     */
    private function buildPagedUsers(): void
    {
        $this->totalAllUsers = count($this->allUsers);
        $this->totalUsers = count($this->users);
        $offset = $this->applyPagination($this->totalUsers);
        $this->pagedUsers = array_slice($this->users, $offset, $this->perPage);

        foreach ($this->pagedUsers as $key => $user) {
            if ($user['username'] !== null) $this->pagedUsers[$key]['username'] = AppController::sanitize($user['username']);
            $this->pagedUsers[$key]['email'] = AppController::sanitize($user['email']);
            if ($user['first_name'] !== null) $this->pagedUsers[$key]['first_name'] = AppController::sanitize($user['first_name']);
            if ($user['last_name'] !== null) $this->pagedUsers[$key]['last_name'] = AppController::sanitize($user['last_name']);
            if ($user['role_name'] !== null) $this->pagedUsers[$key]['role_name'] = AppController::sanitize($user['role_name']);
        }
    }

    /**
     * Computes sort/filter params and other view-facing state.
     *
     * @return void
     */
    private function prepareViewData(): void
    {
        $this->currentUserId = (int)SessionController::get('user')['id'];
        $this->activeSortParams = $this->sortColumn ? ['sort' => $this->sortColumn, 'dir' => $this->sortDirection] : [];
        $this->activeFilterParams = array_filter([
            'search' => $this->search,
            'per_page' => $this->perPage !== self::PER_PAGE_OPTIONS[0] ? $this->perPage : null,
            'role' => $this->filterRole,
            'status' => $this->filterStatus,
            'verified' => $this->filterVerified,
        ], static fn(mixed $v): bool => $v !== '' && $v !== null);
        $this->activeQueryParams = $this->activeSortParams + $this->activeFilterParams;
        $this->hasActiveFilters = $this->search !== '' || $this->filterRole !== '' || $this->filterStatus !== '' || $this->filterVerified !== '';
    }

    /**
     * Validates and creates a new user account, then sends a welcome email.
     *
     * @return void
     */
    private function createUser(): void
    {
        if (
            !FormController::validate('username', ['maxLength' => MAX_USERNAME_LENGTH]) ||
            !FormController::validate('first_name', ['maxLength' => MAX_NAME_LENGTH]) ||
            !FormController::validate('last_name', ['maxLength' => MAX_NAME_LENGTH]) ||
            !FormController::validate('email', ['required', 'maxLength' => MAX_EMAIL_LENGTH, 'type' => 'email']) ||
            !FormController::validate('role', ['required'])
        ) return;

        if (AuthController::checkEmail($_POST['email'])) {
            $_POST['email'] = '';
            FormController::addAlert('An account with this email already exists!', AlertType::WARNING);
            return;
        }

        DB::insert(
            INTO: 'users',
            VALUES: [
                'username' => $_POST['username'] ?: null,
                'first_name' => $_POST['first_name'] ?: null,
                'last_name' => $_POST['last_name'] ?: null,
                'email' => $_POST['email'],
                'password' => password_hash($this->generatedPassword, PASSWORD_HASH_ALGO, PASSWORD_HASH_OPTIONS),
                'must_change_password' => 1,
            ]
        );

        $id = AuthController::getUserIdByEmail($_POST['email']);

        $roleRecord = DB::single(
            SELECT: 'id',
            FROM: 'roles',
            WHERE: [
                'name' => $_POST['role']
            ]
        );
        if ($roleRecord) DB::insert(
            INTO: 'user_roles',
            VALUES: [
                'user_id' => $id,
                'role_id' => $roleRecord['id']
            ]
        );

        AuthController::sendCreatedUserMail($_POST['email'], $this->generatedPassword);

        PageController::redirect('admin/users');
        AlertController::globalAlert('Success! The user has been created!', AlertType::SUCCESS, 4);
    }

    /**
     * Dispatches the POST request to the appropriate action handler.
     *
     * @return void
     */
    private function post(): void
    {
        match ($this->subAction) {
            'edit' => $this->updateUser(),
            'delete' => $this->deleteUser($this->user['id']),
            'purge' => $this->purgeUser($this->user['id']),
            'restore' => $this->restoreUser($this->user['id']),
            default => null
        };
    }

    /**
     * Validates and updates the target user's profile fields and role assignment.
     *
     * @return void
     */
    private function updateUser(): void
    {
        if (
            !FormController::validate('username', ['maxLength' => MAX_USERNAME_LENGTH]) ||
            !FormController::validate('first_name', ['maxLength' => MAX_NAME_LENGTH]) ||
            !FormController::validate('last_name', ['maxLength' => MAX_NAME_LENGTH]) ||
            !FormController::validate('email', ['required', 'maxLength' => MAX_EMAIL_LENGTH, 'type' => 'email']) ||
            !FormController::validate('role', ['required'])
        ) return;

        $id = $this->user['id'];

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

        $roleRecord = DB::single(
            SELECT: 'id',
            FROM: 'roles',
            WHERE: [
                'name' => $_POST['role']
            ]
        );

        if ($roleRecord) {
            if (DB::exists(
                FROM: 'user_roles',
                WHERE: [
                    'user_id' => $id
                ]
            )) DB::update(
                UPDATE: 'user_roles',
                SET: [
                    'role_id' => $roleRecord['id']
                ],
                WHERE: [
                    'user_id' => $id
                ]
            );
            else DB::insert(
                INTO: 'user_roles',
                VALUES: [
                    'user_id' => $id,
                    'role_id' => $roleRecord['id']
                ]
            );
        }

        PageController::redirect('admin/users');
        AlertController::globalAlert('Success! The user has been updated!', AlertType::SUCCESS, 4);
    }

    /**
     * Soft-deletes a user by marking them inactive.
     *
     * @param int $id User ID to delete
     *
     * @return void
     */
    private function deleteUser(int $id): void
    {
        DB::update(
            UPDATE: 'users',
            SET: [
                'is_active' => 0,
                'deleted_at' => date('Y-m-d H:i:s')
            ],
            WHERE: compact('id')
        );
        PageController::redirect('admin/users');
        AlertController::globalAlert('User successfully deleted!', AlertType::SUCCESS, 4);
    }

    /**
     * Permanently removes a user and all their data from the database.
     *
     * @param int $id User ID to purge
     *
     * @return void
     */
    private function purgeUser(int $id): void
    {
        DB::delete(
            FROM: 'users',
            WHERE: compact('id')
        );
        PageController::redirect('admin/users');
        AlertController::globalAlert('User permanently deleted!', AlertType::SUCCESS, 4);
    }

    /**
     * Restores a soft-deleted user, redirecting away if the user is already active.
     *
     * @param int $id User ID to restore
     *
     * @return void
     */
    private function restoreUser(int $id): void
    {
        if ($this->user['is_active']) {
            PageController::redirect('admin/users', 2);
            return;
        }

        DB::update(
            UPDATE: 'users',
            SET: [
                'is_active' => 1,
                'deleted_at' => null
            ],
            WHERE: compact('id')
        );
        PageController::redirect('admin/users');
        AlertController::globalAlert('User successfully restored!', AlertType::SUCCESS, 4);
    }

    /**
     * Renders the table data as JSON for the front-end.
     *
     * @return void
     * @throws JsonException
     *
     */
    final public function api(): void
    {
        header('Content-Type: application/json');

        echo json_encode([
            'thead' => $this->renderThead(),
            'tbody' => $this->renderTbody(),
            'pagination' => $this->renderPagination(),
            'info' => $this->renderPaginationInfo(),
            'total' => $this->totalUsers,
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * Renders the table header row with sortable column links.
     *
     * @return string
     */
    private function renderThead(): string
    {
        $html = '<tr>';

        foreach ($this->tableColumns as $column) {
            $sortParams = array_merge($this->activeFilterParams, $this->getNextSortParams($column));
            $href = '/admin/users' . ($sortParams ? '?' . http_build_query($sortParams) : '');
            $widthAttr = $column['width'] ? ' data-width="' . $column['width'] . '"' : '';
            $sortClass = !empty($column['sortable']) ? 'sortable ' . $this->getColumnSortClass($column) : '';

            $html .= '<th class="' . $sortClass . '"' . $widthAttr . '>';
            $html .= !empty($column['sortable'])
                ? '<a class="table-sort-link" href="' . $href . '">' . $column['label'] . '</a>'
                : '<p class="table-header-label">' . $column['label'] . '</p>';
            $html .= '</th>';
        }

        return $html . '</tr>';
    }

    /**
     * Returns the query parameters that would apply if this column header is clicked.
     *
     * @param array<string, mixed> $column Column definition
     *
     * @return array<string, mixed>
     */
    public function getNextSortParams(array $column): array
    {
        if (empty($column['sortable'])) return [];
        if ($this->sortColumn !== $column['key']) return ['sort' => $column['key'], 'dir' => self::SORT_ASC];
        if ($this->sortDirection === self::SORT_ASC) return ['sort' => $column['key'], 'dir' => self::SORT_DESC];
        return [];
    }

    /**
     * Returns the CSS sort class for the given column based on the active sort state.
     *
     * @param array<string, mixed> $column Column definition
     *
     * @return string
     */
    public function getColumnSortClass(array $column): string
    {
        if (empty($column['sortable']) || $this->sortColumn !== $column['key']) return '';
        return $this->sortDirection === self::SORT_ASC ? 'sort-asc' : 'sort-desc';
    }

    /**
     * Renders all table body rows for the current page.
     *
     * @return string
     */
    private function renderTbody(): string
    {
        $html = '';

        foreach ($this->pagedUsers as $user) {
            $html .= '<tr class="' . (!$user['is_active'] ? 'deleted' : '') . '">';

            foreach ($this->tableColumns as $column) {
                if ($column['key'] === 'actions') {
                    if ($user['id'] !== $this->currentUserId) {
                        $uid = $user['id'];
                        $uname = $user['username'] ?? '-';
                        $uemail = $user['email'];
                        $active = $user['is_active'] ? '1' : '0';
                        $html .= '<td class="table-actions"><div class="row g-col-0.5 center-y">';

                        if ($user['is_active']) {
                            $html .= '<a class="col table-action f-0" href="/admin/users/edit?id=' . $uid . '"><i class="fas fa-pen"></i></a>';
                            $html .= '<button class="col table-action delete f-0" type="button" data-modal-delete data-user-id="' . $uid . '" data-user-username="' . $uname . '" data-user-email="' . $uemail . '" data-user-active="' . $active . '"><i class="fas fa-trash"></i></button>';
                        } else {
                            $html .= '<button class="col table-action restore f-0" type="button" data-modal-restore data-user-id="' . $uid . '" data-user-username="' . $uname . '" data-user-email="' . $uemail . '"><i class="fas fa-wrench"></i></button>';
                            $html .= '<button class="col table-action purge f-0" type="button" data-modal-delete data-user-id="' . $uid . '" data-user-username="' . $uname . '" data-user-email="' . $uemail . '" data-user-active="' . $active . '"><i class="fas fa-skull"></i></button>';
                        }

                        $html .= '</div></td>';
                    } else $html .= '<td><span class="text-muted">-</span></td>';
                } else $html .= '<td>' . $this->renderCell($column, $user) . '</td>';
            }

            $html .= '</tr>';
        }

        return $html;
    }

    /**
     * Renders the cell content for a given column and user row.
     *
     * @param array<string, mixed> $column Column definition
     * @param array<string, mixed> $user   User row
     *
     * @return string
     */
    public function renderCell(array $column, array $user): string
    {
        $check = '<i class="fas fa-check"></i>';
        $times = '<i class="fas fa-times"></i>';
        $muted = static fn(string $v): string => $v ?: '<span class="text-muted">-</span>';

        return match ($column['key']) {
            'id' => (string)$user['id'],
            'username' => $muted($user['username'] ?? ''),
            'email' => $user['email'],
            'first_name' => $muted($user['first_name'] ?? ''),
            'last_name' => $muted($user['last_name'] ?? ''),
            'role' => $muted($user['role_name'] ?? ''),
            'is_verified' => !empty($user['is_verified']) ? $check : $times,
            'must_change_password' => $user['must_change_password'] ? $check : $times,
            'last_login' => $muted($user['last_login'] ?? ''),
            'created_at' => $user['created_at'],
            'last_update' => $user['last_update'],
            'is_active' => $user['is_active'] ? $check : $times,
            'deleted_at' => $muted($user['deleted_at'] ?? ''),
            default => '',
        };
    }

    /**
     * Renders previous/next pagination links with active query parameters preserved.
     *
     * @return string
     */
    private function renderPagination(): string
    {
        $cls = 'class="col link lh-1 g-col-0.5 center f-0"';
        $prev = '/admin/users?' . http_build_query(['page' => $this->page - 1] + $this->activeQueryParams);
        $next = '/admin/users?' . http_build_query(['page' => $this->page + 1] + $this->activeQueryParams);

        return '<a ' . $cls . ' href="' . $prev . '" ' . ($this->page > 0 ? '' : 'inert') . '><i class="fas fa-chevron-left"></i>Previous</a>' . '<a ' . $cls . ' href="' . $next . '" ' . ($this->page < $this->maxPage ? '' : 'inert') . '>Next<i class="fas fa-chevron-right"></i></a>';
    }

    /**
     * Renders the "X - Y of Z users" info string for the current page.
     *
     * @return string
     */
    private function renderPaginationInfo(): string
    {
        return $this->startIndex . ' - ' . $this->endIndex . ' of ' . $this->totalUsers . ($this->hasActiveFilters ? ' / ' . $this->totalAllUsers : '') . ' users';
    }
}
