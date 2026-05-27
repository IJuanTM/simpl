<?php

declare(strict_types=1);

namespace app\Pages\Admin;

use app\Controllers\AlertController;
use app\Controllers\AuthController;
use app\Controllers\FormController;
use app\Controllers\PageController;
use app\Controllers\SessionController;
use app\Database\DB;
use app\Enums\AlertType;
use app\Enums\Role;
use app\Models\Page;
use JsonException;

class Users
{
    private const string SORT_ASC = 'asc';
    private const string SORT_DESC = 'desc';
    private const array PER_PAGE_OPTIONS = [10, 25, 50, 100];

    public ?string $subAction;
    public array $user;
    public array $allUsers = [];
    public array $users = [];
    public array $pagedUsers = [];
    public array $tableColumns = [];
    public array $visibleColumns = [];
    public string $generatedPassword = '';
    public int $page = 0;
    public int $perPage = 10;
    public array $perPageOptions = self::PER_PAGE_OPTIONS;
    public string $search = '';
    public string $filterRole = '';
    public string $filterStatus = '';
    public string $filterVerified = '';
    public bool $hasActiveFilters = false;
    public ?string $sortColumn = 'id';
    public ?string $sortDirection = self::SORT_ASC;
    public int $totalUsers = 0;
    public int $totalAllUsers = 0;
    public int $maxPage = 0;
    public int $startIndex = 0;
    public int $endIndex = 0;
    public int $currentUserId = 0;
    public array $activeSortParams = [];
    public array $activeFilterParams = [];
    public array $activeQueryParams = [];
    public array $availableRoles = [];

    public function __construct(Page $page)
    {
        $this->subAction = $page->subpage(1);
        $this->availableRoles = DB::select(
            SELECT: '*',
            FROM: 'roles',
            ORDER_BY: 'name ASC'
        );
        $this->tableColumns = self::getTableColumns();
        $this->visibleColumns = $this->tableColumns;

        if ($page->param('page') !== null) $this->page = (int)$page->param('page');
        if ($page->param('per_page') !== null && in_array((int)$page->param('per_page'), self::PER_PAGE_OPTIONS, true)) $this->perPage = (int)$page->param('per_page');
        if ($page->param('search') !== null) $this->search = trim((string)$page->param('search'));
        if ($page->param('role') !== null) $this->filterRole = trim((string)$page->param('role'));
        if ($page->param('status') !== null && in_array($page->param('status'), ['active', 'inactive'], true)) $this->filterStatus = (string)$page->param('status');
        if ($page->param('verified') !== null && in_array($page->param('verified'), ['yes', 'no'], true)) $this->filterVerified = (string)$page->param('verified');

        $this->resolveSort($page->params);
        $this->loadUsers();
        $this->filterUsers();
        $this->sortUsers();
        $this->buildPagedUsers();
        $this->prepareViewData();

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

            if ($this->user['id'] === SessionController::get('user')['id']) {
                PageController::redirect('admin/users', 2);
                return;
            }

            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
                $this->post($page);
                return;
            }

            // delete/purge/restore are POST-only (modal); redirect GET requests
            if (in_array($subAction, ['delete', 'purge', 'restore'])) PageController::redirect('admin/users');
        }
    }

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

    private function resolveSort(array $params): void
    {
        $sortable = array_column(array_filter($this->tableColumns, static fn(array $c): bool => !empty($c['sortable'])), 'key');
        if (!isset($params['sort']) || !in_array($params['sort'], $sortable, true)) return;

        $this->sortColumn = $params['sort'];
        $dir = isset($params['dir']) ? strtolower((string)$params['dir']) : self::SORT_ASC;
        $this->sortDirection = in_array($dir, [self::SORT_ASC, self::SORT_DESC], true) ? $dir : self::SORT_ASC;
    }

    private function loadUsers(): void
    {
        $this->allUsers = DB::select(SELECT: '*', FROM: 'users');

        foreach ($this->allUsers as $key => $user) {
            $roleId = DB::single(
                SELECT: 'role_id',
                FROM: 'user_roles',
                WHERE: ['user_id' => $user['id']]
            )['role_id'] ?? null;
            $roleName = $roleId ? DB::single(
                SELECT: 'name',
                FROM: 'roles',
                WHERE: ['id' => $roleId]
            )['name'] ?? null : null;

            $this->allUsers[$key]['role'] = $roleName ? Role::tryFrom($roleName) : null;
            $this->allUsers[$key]['role_name'] = $roleName;
            $this->allUsers[$key]['is_verified'] = AuthController::isVerified($user['id']) ? 1 : 0;
        }

        $this->users = $this->allUsers;
    }

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

    private function buildPagedUsers(): void
    {
        $this->maxPage = max(0, (int)ceil(count($this->users) / $this->perPage) - 1);
        $this->page = max(0, min($this->page, $this->maxPage));
        $this->pagedUsers = array_slice($this->users, $this->page * $this->perPage, $this->perPage);
    }

    private function prepareViewData(): void
    {
        $this->totalAllUsers = count($this->allUsers);
        $this->totalUsers = count($this->users);
        $this->maxPage = max(0, (int)ceil($this->totalUsers / $this->perPage) - 1);
        $this->startIndex = $this->totalUsers === 0 ? 0 : ($this->page * $this->perPage) + 1;
        $this->endIndex = min(($this->page + 1) * $this->perPage, $this->totalUsers);
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

    private function createUser(): void
    {
        if (
            !FormController::validate('username', ['maxLength' => MAX_USERNAME_LENGTH]) ||
            !FormController::validate('first_name', ['maxLength' => MAX_NAME_LENGTH]) ||
            !FormController::validate('last_name', ['maxLength' => MAX_NAME_LENGTH]) ||
            !FormController::validate('email', ['required', 'maxLength' => MAX_EMAIL_LENGTH, 'type' => 'email']) ||
            !FormController::validate('role', ['required'])
        ) return;

        FormController::sanitizeFields(['username', 'first_name', 'last_name', 'email']);

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
            WHERE: ['name' => $_POST['role']]
        );
        if ($roleRecord) DB::insert(
            INTO: 'user_roles',
            VALUES: ['user_id' => $id, 'role_id' => $roleRecord['id']]
        );

        AuthController::sendCreatedUserMail($_POST['email'], $this->generatedPassword);

        PageController::redirect('admin/users');
        AlertController::globalAlert('Success! The user has been created!', AlertType::SUCCESS, 4);
    }

    private function post(Page $page): void
    {
        match ($this->subAction) {
            'edit' => $this->updateUser($page),
            'delete' => $this->deleteUser($this->user['id']),
            'purge' => $this->purgeUser($this->user['id']),
            'restore' => $this->user['is_active']
                ? PageController::redirect('admin/users', 2)
                : $this->restoreUser($this->user['id']),
            default => null,
        };
    }

    private function updateUser(Page $page): void
    {
        if (
            !FormController::validate('username', ['maxLength' => MAX_USERNAME_LENGTH]) ||
            !FormController::validate('first_name', ['maxLength' => MAX_NAME_LENGTH]) ||
            !FormController::validate('last_name', ['maxLength' => MAX_NAME_LENGTH]) ||
            !FormController::validate('email', ['required', 'maxLength' => MAX_EMAIL_LENGTH, 'type' => 'email']) ||
            !FormController::validate('role', ['required'])
        ) return;

        FormController::sanitizeFields(['username', 'first_name', 'last_name', 'email']);

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
            WHERE: ['name' => $_POST['role']]
        );
        if ($roleRecord) {
            if (DB::exists(FROM: 'user_roles', WHERE: ['user_id' => $id])) DB::update(
                UPDATE: 'user_roles',
                SET: ['role_id' => $roleRecord['id']],
                WHERE: ['user_id' => $id]
            );
            else DB::insert(
                INTO: 'user_roles',
                VALUES: ['user_id' => $id, 'role_id' => $roleRecord['id']]
            );
        }

        PageController::redirect('admin/users');
        AlertController::globalAlert('Success! The user has been updated!', AlertType::SUCCESS, 4);
    }

    private function deleteUser(int $id): void
    {
        DB::update(
            UPDATE: 'users',
            SET: ['is_active' => 0, 'deleted_at' => date('Y-m-d H:i:s')],
            WHERE: compact('id')
        );
        PageController::redirect('admin/users');
        AlertController::globalAlert('User successfully deleted!', AlertType::SUCCESS, 4);
    }

    private function purgeUser(int $id): void
    {
        DB::delete(
            FROM: 'users',
            WHERE: compact('id')
        );
        PageController::redirect('admin/users');
        AlertController::globalAlert('User permanently deleted!', AlertType::SUCCESS, 4);
    }

    private function restoreUser(int $id): void
    {
        DB::update(
            UPDATE: 'users',
            SET: ['is_active' => 1, 'deleted_at' => null],
            WHERE: compact('id')
        );
        PageController::redirect('admin/users');
        AlertController::globalAlert('User successfully restored!', AlertType::SUCCESS, 4);
    }

    /**
     * @throws JsonException
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
                ? '<a class="table-sort-link" href="' . $href . '">' . htmlspecialchars($column['label']) . '</a>'
                : '<p class="table-header-label">' . htmlspecialchars($column['label']) . '</p>';
            $html .= '</th>';
        }

        return $html . '</tr>';
    }

    public function getNextSortParams(array $column): array
    {
        if (empty($column['sortable'])) return [];
        if ($this->sortColumn !== $column['key']) return ['sort' => $column['key'], 'dir' => self::SORT_ASC];
        if ($this->sortDirection === self::SORT_ASC) return ['sort' => $column['key'], 'dir' => self::SORT_DESC];
        return [];
    }

    public function getColumnSortClass(array $column): string
    {
        if (empty($column['sortable']) || $this->sortColumn !== $column['key']) return '';
        return $this->sortDirection === self::SORT_ASC ? 'sort-asc' : 'sort-desc';
    }

    private function renderTbody(): string
    {
        $html = '';

        foreach ($this->pagedUsers as $user) {
            $html .= '<tr class="' . (!$user['is_active'] ? 'deleted' : '') . '">';

            foreach ($this->tableColumns as $column) {
                if ($column['key'] === 'actions') {
                    if ($user['id'] !== $this->currentUserId) {
                        $uid = $user['id'];
                        $uname = htmlspecialchars($user['username'] ?? '-', ENT_QUOTES);
                        $uemail = htmlspecialchars($user['email'], ENT_QUOTES);
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

    private function renderPagination(): string
    {
        $cls = 'class="col link lh-1 g-col-0.5 center f-0"';
        $prev = '/admin/users?' . http_build_query(['page' => $this->page - 1] + $this->activeQueryParams);
        $next = '/admin/users?' . http_build_query(['page' => $this->page + 1] + $this->activeQueryParams);

        return '<a ' . $cls . ' href="' . $prev . '" ' . ($this->page > 0 ? '' : 'inert') . '><i class="fas fa-chevron-left"></i>Previous</a>'
            . '<a ' . $cls . ' href="' . $next . '" ' . ($this->page < $this->maxPage ? '' : 'inert') . '>Next<i class="fas fa-chevron-right"></i></a>';
    }

    private function renderPaginationInfo(): string
    {
        return $this->startIndex . ' - ' . $this->endIndex . ' of ' . $this->totalUsers
            . ($this->hasActiveFilters ? ' / ' . $this->totalAllUsers : '') . ' users';
    }
}
