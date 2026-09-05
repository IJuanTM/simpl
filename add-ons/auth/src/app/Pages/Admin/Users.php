<?php

declare(strict_types=1);

namespace app\Pages\Admin;

use app\Controllers\AppController;
use app\Controllers\AuthController;
use app\Controllers\FormController;
use app\Controllers\PageController;
use app\Controllers\SessionController;
use app\Database\DB;
use app\Enums\AlertType;
use app\Enums\Role;
use app\Enums\TokenType;
use app\Enums\UserStatus;
use app\Models\Page;
use app\Pages\Admin\Traits\AdminTableTrait;
use PDOException;

/**
 * Admin page: handles user listing with search, filtering, sorting, and pagination, as well
 * as user creation, editing, soft-deletion, restoration, and permanent purge.
 * The table data is served via an API endpoint used by the front-end JS.
 */
class Users
{
    use AdminTableTrait;

    private const string SORT_ASC = 'asc';
    private const array PER_PAGE_OPTIONS = [25, 50, 100, 250];

    // Maps a sortable column key to the SQL expression ORDER BY should sort on.
    // Either a real column or a SELECT-list alias - MySQL allows ordering by either.
    private const array SORT_COLUMNS = [
        'id' => 'users.id',
        'username' => 'users.username',
        'email' => 'users.email',
        'first_name' => 'users.first_name',
        'last_name' => 'users.last_name',
        'role' => 'role_name',
        'is_verified' => 'is_verified',
        'must_change_password' => 'users.must_change_password',
        'last_login' => 'users.last_login',
        'created_at' => 'users.created_at',
        'last_update' => 'users.last_update',
        'status' => 'users.status',
        'inactive_since' => 'users.inactive_since',
    ];

    public ?string $subAction;
    public array $user = [];
    public array $pagedUsers = [];
    public string $generatedPassword = '';
    public int $perPage = 25;
    public array $perPageOptions = self::PER_PAGE_OPTIONS;
    public int $totalAllUsers = 0;
    public int $currentUserId = 0;
    public array $availableRoles = [];

    public function __construct(Page $page)
    {
        $this->route($page);
    }

    /**
     * Loads the available roles, sets up the table columns, and runs create/edit/delete
     * sub-actions or the default listing based on the URL.
     *
     * @param Page $page
     *
     * @return void
     */
    private function route(Page $page): void
    {
        $this->subAction = $page->subpage(1);
        $this->currentUserId = (int)SessionController::get('user')['id'];
        $this->availableRoles = DB::select(
            SELECT: '*',
            FROM: 'roles',
            ORDER_BY: 'name ASC'
        );

        foreach ($this->availableRoles as $key => $r) $this->availableRoles[$key]['name'] = AppController::sanitize($r['name']);

        $this->tableColumns = self::getTableColumns();
        $this->itemLabel = 'users';
        $this->sortColumn = 'id';
        $this->sortDirection = self::SORT_ASC;
        $this->filterDefinitions = [
            'role' => [],
            'status' => ['active', 'deactivated', 'deleted'],
            'verified' => ['yes', 'no'],
        ];

        $subAction = $this->subAction;

        // Only the main listing needs the full table pipeline - sub-actions look up one row.
        if ($subAction === null) {
            $this->initTable($page);
            $this->loadUsers();
            return;
        }

        if ($subAction === 'create') {
            $isSubmit = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit']);

            // Generated once per form round-trip and resubmitted via the readonly password field, so the value the admin sees on screen is always the one that actually gets applied.
            // Regenerating fresh on the POST request itself would silently create the account with a different password than what was displayed.
            $password = $isSubmit && !empty($_POST['password']) ? $_POST['password'] : AuthController::generatePassword();

            if ($password === null) {
                FormController::addAlert('Could not generate a password. Please try again.', AlertType::ERROR);
                PageController::redirect('admin/users', 2);
                return;
            }

            $this->generatedPassword = $password;
            if ($isSubmit) $this->createUser();
            return;
        }

        if (in_array($subAction, ['edit', 'delete', 'restore', 'purge'])) {
            $user = $this->requireRecord($page, 'admin/users', static fn(int $id): ?array => DB::single(
                SELECT: ['users.*', 'roles.name AS role_name'],
                FROM: 'users',
                JOIN: [
                    ['id', ['user_roles', 'user_id']],
                    [['user_roles', 'role_id'], ['roles', 'id']],
                ],
                WHERE: ['users.id' => $id]
            ));
            if ($user === null) return;

            $this->user = $user;
            if ($this->user['username'] !== null) $this->user['username'] = AppController::sanitize($this->user['username']);
            if ($this->user['email'] !== null) $this->user['email'] = AppController::sanitize($this->user['email']);
            if ($this->user['first_name'] !== null) $this->user['first_name'] = AppController::sanitize($this->user['first_name']);
            if ($this->user['last_name'] !== null) $this->user['last_name'] = AppController::sanitize($this->user['last_name']);
            if ($this->user['role_name'] !== null) $this->user['role_name'] = AppController::sanitize($this->user['role_name']);

            if ($this->user['id'] === $this->currentUserId) {
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
        return self::buildColumns([
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
            ['status', 'Status', true, 128, true],
            ['inactive_since', 'Inactive since', true, 160, true],
            ['actions', 'Actions', false, null, true],
        ]);
    }

    /**
     * Loads the current page of users matching the active search/filters, entirely in SQL
     * (search/filter/sort/pagination pushed down to the query, mirroring LoginAttempts) rather
     * than loading the whole table and processing it in PHP on every render and every api() poll.
     *
     * @return void
     */
    private function loadUsers(): void
    {
        $join = [
            ['id', ['user_roles', 'user_id']],
            [['user_roles', 'role_id'], ['roles', 'id']],
        ];

        $where = [];
        if ($this->filters['role'] !== '') $where['roles.name'] = $this->filters['role'];
        if ($this->filters['status'] !== '') $where['users.status'] = $this->filters['status'];

        if ($this->filters['verified'] !== '') {
            // Users with an outstanding verification token are the unverified ones.
            $unverifiedIds = array_column(DB::select(
                SELECT: 'DISTINCT user_id',
                FROM: 'tokens',
                WHERE: ['type' => TokenType::VERIFICATION->value]
            ), 'user_id');

            $where['users.id'] = [$this->filters['verified'] === 'yes' ? 'NOT IN' : 'IN', $unverifiedIds];
        }

        $orWhere = [];
        if ($this->search !== '') {
            // Escape LIKE wildcards so a literal '%' or '_' in the search term isn't treated as a pattern.
            $escapedSearch = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $this->search);
            $like = ['LIKE', '%' . $escapedSearch . '%'];
            $orWhere = [
                'users.id' => $like,
                'users.username' => $like,
                'users.email' => $like,
                'users.first_name' => $like,
                'users.last_name' => $like,
            ];
        }

        $this->totalAllUsers = DB::count(FROM: 'users');

        $total = DB::count(
            FROM: 'users',
            JOIN: $join,
            WHERE: $where,
            OR_WHERE: $orWhere
        );

        $offset = $this->applyPagination($total);

        $orderBy = $this->sortColumn && $this->sortDirection
            ? (self::SORT_COLUMNS[$this->sortColumn] ?? 'users.id') . ' ' . strtoupper($this->sortDirection)
            : null;

        $rows = DB::select(
            SELECT: [
                'users.*',
                'roles.name AS role_name',
                '(CASE WHEN EXISTS (SELECT 1 FROM tokens WHERE tokens.user_id = users.id AND tokens.type = \'verification\') THEN 0 ELSE 1 END) AS is_verified',
            ],
            FROM: 'users',
            JOIN: $join,
            WHERE: $where,
            OR_WHERE: $orWhere,
            ORDER_BY: $orderBy,
            LIMIT: $this->perPage,
            OFFSET: $offset
        );

        foreach ($rows as $key => $user) {
            $rows[$key]['role'] = isset($user['role_name']) ? Role::tryFrom($user['role_name']) : null;
            $rows[$key]['is_verified'] = (int)$user['is_verified'];

            if ($user['username'] !== null) $rows[$key]['username'] = AppController::sanitize($user['username']);
            $rows[$key]['email'] = AppController::sanitize($user['email']);
            if ($user['first_name'] !== null) $rows[$key]['first_name'] = AppController::sanitize($user['first_name']);
            if ($user['last_name'] !== null) $rows[$key]['last_name'] = AppController::sanitize($user['last_name']);
            if ($user['role_name'] !== null) $rows[$key]['role_name'] = AppController::sanitize($user['role_name']);
        }

        $this->pagedUsers = $rows;
    }

    /**
     * Validates and creates a new user account, then sends a welcome email.
     *
     * @return void
     */
    private function createUser(): void
    {
        if (!$this->validateUserFields()) return;

        if ($_POST['username'] !== '' && AuthController::getUserIdByUsername($_POST['username']) !== null) {
            $_POST['username'] = '';
            FormController::addAlert('That username is already taken!', AlertType::WARNING);
            return;
        }

        if (AuthController::checkEmail($_POST['email'])) {
            $_POST['email'] = '';
            FormController::addAlert('An account with this email already exists!', AlertType::WARNING);
            return;
        }

        $roleRecord = $this->resolveRole();
        if (!$roleRecord) return;

        $this->runInTransaction(function () use ($roleRecord): void {
            DB::insert(
                INTO: 'users',
                VALUES: [
                    'username' => $_POST['username'] ?: null,
                    'first_name' => $_POST['first_name'] ?: null,
                    'last_name' => $_POST['last_name'] ?: null,
                    'email' => $_POST['email'],
                    'password' => password_hash($this->generatedPassword, PASSWORD_CONFIG['hash_algo'], PASSWORD_CONFIG['hash_options']),
                    'must_change_password' => 1,
                ]
            );

            DB::insert(
                INTO: 'user_roles',
                VALUES: [
                    'user_id' => (int)DB::lastInsertId(),
                    'role_id' => $roleRecord['id']
                ]
            );
        });

        $result = AuthController::sendCreatedUserMail($_POST['email'], $this->generatedPassword);

        if ($result) PageController::redirectWithAlert('admin/users', 'Success! The user has been created and notified via email!', AlertType::SUCCESS, 4);
        else PageController::redirectWithAlert('admin/users', 'The user has been created! However, there was an issue sending the notification email.', AlertType::ERROR, 8);
    }

    /**
     * Validates the fields shared by create and edit forms.
     *
     * @return bool True when all fields pass validation
     */
    private function validateUserFields(): bool
    {
        return FormController::validate('username', ['maxLength' => MAX_USERNAME_LENGTH]) &&
            FormController::validate('first_name', ['maxLength' => MAX_NAME_LENGTH]) &&
            FormController::validate('last_name', ['maxLength' => MAX_NAME_LENGTH]) &&
            FormController::validate('email', ['required', 'maxLength' => MAX_EMAIL_LENGTH, 'type' => 'email']) &&
            FormController::validate('role', ['required']);
    }

    /**
     * Resolves the submitted role name to its id, alerting when it no longer exists.
     *
     * @return array|null The role row, or null (with an alert already queued) when not found
     */
    private function resolveRole(): ?array
    {
        $roleRecord = DB::single(
            SELECT: 'id',
            FROM: 'roles',
            WHERE: [
                'name' => $_POST['role']
            ]
        );

        if (!$roleRecord) {
            FormController::addAlert('The selected role no longer exists. Please choose another.', AlertType::WARNING);
            return null;
        }

        return $roleRecord;
    }

    /**
     * Runs $fn inside a transaction, rolling back and re-throwing on a PDOException.
     */
    private function runInTransaction(callable $fn): void
    {
        DB::beginTransaction();

        try {
            $fn();
            DB::commit();
        } catch (PDOException $e) {
            DB::rollback();
            throw $e;
        }
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
        if (!$this->validateUserFields()) return;

        $id = (int)$this->user['id'];

        if ($_POST['username'] !== '' && AuthController::usernameTakenByOtherUser($_POST['username'], $id)) {
            $_POST['username'] = $this->user['username'] ?? '';
            FormController::addAlert('That username is already taken!', AlertType::WARNING);
            return;
        }

        if (AuthController::emailTakenByOtherUser($_POST['email'], $id)) {
            $_POST['email'] = $this->user['email'];
            FormController::addAlert('An account with this email already exists!', AlertType::WARNING);
            return;
        }

        $roleRecord = $this->resolveRole();
        if (!$roleRecord) return;

        $this->runInTransaction(function () use ($id, $roleRecord): void {
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
        });

        PageController::redirectWithAlert('admin/users', 'Success! The user has been updated!', AlertType::SUCCESS, 4);
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
        if ($this->blockUnlessActive(requireActive: true)) return;

        DB::update(
            UPDATE: 'users',
            SET: [
                'status' => UserStatus::DELETED->value,
                'inactive_since' => date('Y-m-d H:i:s')
            ],
            WHERE: compact('id')
        );
        PageController::redirectWithAlert('admin/users', 'User successfully deleted!', AlertType::SUCCESS, 4);
    }

    /**
     * Redirects away (without acting) unless the target user's active state matches
     * $requireActive - true for delete (must be active), false for purge/restore (must not be).
     *
     * @param bool $requireActive
     *
     * @return bool True when the redirect fired and the caller should return immediately
     */
    private function blockUnlessActive(bool $requireActive): bool
    {
        return $this->blockIf(
            ($this->user['status'] === UserStatus::ACTIVE->value) !== $requireActive,
            static fn() => PageController::redirect('admin/users', 2)
        );
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
        if ($this->blockUnlessActive(requireActive: false)) return;

        DB::delete(
            FROM: 'users',
            WHERE: compact('id')
        );
        PageController::redirectWithAlert('admin/users', 'User permanently deleted!', AlertType::SUCCESS, 4);
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
        if ($this->blockUnlessActive(requireActive: false)) return;

        DB::update(
            UPDATE: 'users',
            SET: [
                'status' => UserStatus::ACTIVE->value,
                'inactive_since' => null
            ],
            WHERE: compact('id')
        );
        PageController::redirectWithAlert('admin/users', 'User successfully restored!', AlertType::SUCCESS, 4);
    }

    /**
     * Renders a user row's cell for the given column.
     */
    final public function renderCell(array $column, array $row): string
    {
        $check = '<i class="fas fa-check"></i>';
        $times = '<i class="fas fa-times"></i>';
        $muted = static fn(string $v): string => $v ?: '<span class="text-muted">-</span>';
        $isVerified = !empty($row['is_verified']);

        return match ($column['key']) {
            'id' => (string)$row['id'],
            'username' => $muted($row['username'] ?? ''),
            'email' => $row['email'],
            'first_name' => $muted($row['first_name'] ?? ''),
            'last_name' => $muted($row['last_name'] ?? ''),
            'role' => $muted($row['role_name'] ?? ''),
            'is_verified' => $this->renderBadge($isVerified, $isVerified ? 'Verified' : 'Unverified'),
            'must_change_password' => $row['must_change_password'] ? $check : $times,
            'last_login' => $muted($row['last_login'] ?? ''),
            'created_at' => $row['created_at'],
            'last_update' => $row['last_update'],
            'status' => $this->renderBadge($row['status'] === UserStatus::ACTIVE->value, ucfirst((string)$row['status'])),
            'inactive_since' => $muted($row['inactive_since'] ?? ''),
            default => '',
        };
    }

    /**
     * Overrides the trait default to add the "/ totalAllUsers" count when filtered.
     */
    final public function renderPaginationInfo(): string
    {
        return $this->startIndex . ' - ' . $this->endIndex . ' of ' . $this->total . ($this->hasActiveFilters ? ' / ' . $this->totalAllUsers : '') . ' users';
    }

    /**
     * Row data for the current page.
     */
    private function pageRows(): array
    {
        return $this->pagedUsers;
    }

    /**
     * Overrides the trait default: soft-deleted (non-active) users get a "deleted" row class.
     */
    private function rowClass(array $row): string
    {
        return $row['status'] === UserStatus::ACTIVE->value ? '' : 'deleted';
    }

    /**
     * Edit/delete for active users, restore/purge otherwise; no actions on the current user.
     */
    private function renderActionsCell(array $row): string
    {
        if ($row['id'] === $this->currentUserId) return '<td><span class="text-muted">-</span></td>';

        $isActive = $row['status'] === UserStatus::ACTIVE->value;
        $uid = $row['id'];
        $uname = $row['username'] ?? '-';
        $uemail = $row['email'];

        $html = '<td class="table-actions"><div class="row g-col-0.5 center-y">';

        if ($isActive) {
            $html .= '<a class="col table-action f-0" href="/admin/users/edit?id=' . $uid . '" aria-label="Edit user ' . $uemail . '"><i class="fas fa-pen"></i></a>';
            $html .= '<button class="col table-action delete f-0" type="button" data-cooldown="' . UI_BUTTON_COOLDOWN . '" data-modal-delete data-user-id="' . $uid . '" data-user-username="' . $uname . '" data-user-email="' . $uemail . '" aria-label="Delete user ' . $uemail . '"><i class="fas fa-trash"></i></button>';
        } else {
            $html .= '<button class="col table-action restore f-0" type="button" data-cooldown="' . UI_BUTTON_COOLDOWN . '" data-modal-restore data-user-id="' . $uid . '" data-user-username="' . $uname . '" data-user-email="' . $uemail . '" aria-label="Restore user ' . $uemail . '"><i class="fas fa-wrench"></i></button>';
            $html .= '<button class="col table-action purge f-0" type="button" data-cooldown="' . UI_BUTTON_COOLDOWN . '" data-modal-purge data-user-id="' . $uid . '" data-user-username="' . $uname . '" data-user-email="' . $uemail . '" aria-label="Permanently delete user ' . $uemail . '"><i class="fas fa-skull"></i></button>';
        }

        return $html . '</div></td>';
    }

    /**
     * Base route for pagination/sort-link hrefs.
     */
    private function routePath(): string
    {
        return '/admin/users';
    }
}
