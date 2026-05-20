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
use app\Enums\Role;
use app\Models\Page;
use JsonException;

/**
 * UsersPage
 *
 * Administrative user management including create/edit/delete/restore and
 * password generation. Requires admin role to access.
 */
class UsersPage
{
    private const string SORT_ASC = 'asc';
    private const string SORT_DESC = 'desc';
    private const array PER_PAGE_OPTIONS = [10, 25, 50, 100];

    public int $page = 0;
    public int $perPage = 10;
    public array $perPageOptions = self::PER_PAGE_OPTIONS;

    public array $user;
    public array $allUsers = [];
    public array $users;

    public string $search = '';

    public array $pagedUsers = [];
    public array $tableColumns = [];
    public array $visibleColumns = [];

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

    public string $generatedPassword = '';

    public function __construct(Page $page)
    {
        AuthController::requireAuth([Role::ADMIN]);

        $this->tableColumns = self::getTableColumns();
        $this->visibleColumns = $this->tableColumns;

        // Get parameters from URL
        $params = $page->urlArr['params'] ?? [];
        if (isset($params['page'])) $this->page = (int)$params['page'];
        if (isset($params['per_page']) && in_array((int)$params['per_page'], self::PER_PAGE_OPTIONS, true)) $this->perPage = (int)$params['per_page'];
        if (isset($params['search'])) $this->search = trim((string)$params['search']);

        $this->resolveSort($params);
        $this->loadUsers();
        $this->filterUsers();
        $this->sortUsers();
        $this->buildPagedUsers();
        $this->prepareViewData();

        if (!isset($page->urlArr['subpages'][0])) return;

        $subpage = $page->urlArr['subpages'][0];

        if ($subpage === 'create') $this->generatedPassword = AuthController::generatePassword();

        // Load user data for edit/delete/restore actions
        if (in_array($subpage, ['edit', 'delete', 'restore'])) {
            // Validate user ID parameter
            if (!isset($params['id'])) {
                PageController::redirect('users', 2);
                return;
            }

            // Find user in users array
            $index = array_search((int)$params['id'], array_column($this->allUsers, 'id'), true);

            // Check if user exists
            if ($index === false) {
                PageController::redirect('users', 2);
                return;
            }

            $this->user = $this->allUsers[$index];

            // Prevent admin from modifying their own account
            if ($this->user['id'] === SessionController::get('user')['id']) PageController::redirect('users', 2);
        }

        // Process form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) $this->post($page);
    }

    /**
     * Retrieves the table columns configuration, including properties such as keys, labels,
     * sortable status, widths, and visibility for each column.
     *
     * @return array An array of associative arrays, where each associative array represents a column
     *               with the following keys: 'key', 'label', 'sortable', 'width', and 'visible'.
     */
    private static function getTableColumns(): array
    {
        // Return default table column configuration
        return array_map(static fn(array $c): array => ['key' => $c[0], 'label' => $c[1], 'sortable' => $c[2], 'width' => $c[3], 'visible' => $c[4],], [
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
     * Resolves sorting parameters and updates the sort column and direction.
     *
     * @param array $params An associative array containing 'sort' and 'dir' keys
     *                       specifying the sorting column and direction respectively.
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
     * Loads all users from the database, enriches user data with role and verification status,
     * and assigns the prepared user dataset to the users property.
     *
     * @return void
     */
    private function loadUsers(): void
    {
        // Load all users from the database
        $this->allUsers = DB::select('*', 'users');

        // Enrich user data with role and verification status
        foreach ($this->allUsers as $key => $user) {
            // Get user role
            $roleId = DB::single(
                'role_id',
                'user_roles',
                [
                    'user_id' => $user['id']
                ]
            )['role_id'] ?? null;

            $roleName = $roleId ? DB::single(
                'name',
                'roles',
                [
                    'id' => $roleId
                ]
            )['name'] ?? null : null;

            $this->allUsers[$key]['role'] = $roleName ? Role::from($roleName) : null;

            // Check if user is verified
            $this->allUsers[$key]['is_verified'] = AuthController::isVerified($user['id']) ? 1 : 0;
        }

        $this->users = $this->allUsers;
    }

    /**
     * Filters the users list based on a search query. Only users whose attributes
     * match the search term will remain in the filtered list.
     *
     * @return void
     */
    private function filterUsers(): void
    {
        if ($this->search === '') return;

        // Convert search term to lowercase
        $needle = strtolower($this->search);

        // Filter users based on search term
        $this->users = array_values(array_filter($this->allUsers, static fn(array $user): bool => array_any([
            (string)$user['id'],
            (string)($user['username'] ?? ''),
            (string)($user['email'] ?? ''),
            (string)($user['first_name'] ?? ''),
            (string)($user['last_name'] ?? ''),
        ], static fn(string $v): bool => str_contains(strtolower($v), $needle))));
    }

    /**
     * Sorts the users based on the specified sort column and direction.
     *
     * The sorting behavior is determined using the specified column and whether the order is ascending or descending.
     * If the column or direction is not set, the sorting process is skipped.
     *
     * @return void
     */
    private function sortUsers(): void
    {
        if (!$this->sortColumn || !$this->sortDirection) return;

        $col = $this->sortColumn;
        $desc = $this->sortDirection === self::SORT_DESC;

        // Sort users based on the specified column and direction
        usort($this->users, function (array $a, array $b) use ($col, $desc): int {
            $va = $this->getSortValue($a, $col);
            $vb = $this->getSortValue($b, $col);
            $result = is_int($va) && is_int($vb) ? $va <=> $vb : strcmp((string)$va, (string)$vb);
            return $desc ? -$result : $result;
        });
    }

    /**
     * Retrieves the sort value for a user based on the specified column.
     *
     * @param array  $user The user data as an associative array.
     * @param string $column The column name used to determine the sort value.
     *
     * @return int|string The value derived from the specified column in the user data.
     */
    private function getSortValue(array $user, string $column): int|string
    {
        // Determine the sort value based on the specified column
        return match ($column) {
            'id' => (int)$user['id'],
            'username' => strtolower((string)($user['username'] ?? '')),
            'email' => strtolower((string)$user['email']),
            'first_name' => strtolower((string)($user['first_name'] ?? '')),
            'last_name' => strtolower((string)($user['last_name'] ?? '')),
            'role' => $user['role']?->value ?? '',
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
     * Calculates pagination details and segments the users list into a single page of results.
     *
     * @return void
     */
    private function buildPagedUsers(): void
    {
        $this->maxPage = max(0, (int)ceil(count($this->users) / $this->perPage) - 1);
        $this->page = max(0, min($this->page, $this->maxPage));
        $this->pagedUsers = array_slice($this->users, $this->page * $this->perPage, $this->perPage);
    }

    /**
     * Prepares data necessary for rendering the view, including calculations for pagination,
     * current user details, and active query parameters for sorting and filtering.
     *
     * @return void
     */
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
        ], static fn(mixed $v): bool => $v !== '' && $v !== null);
        $this->activeQueryParams = $this->activeSortParams + $this->activeFilterParams;
    }

    /**
     * Handles post actions for the given page, including creating, editing, deleting,
     * and restoring user entities based on the subpage type provided in the request.
     *
     * @param Page $page The Page object containing necessary URL data and subpage details.
     *
     * @return void
     */
    private function post(Page $page): void
    {
        // Get subpage type from URL
        $subpage = $page->urlArr['subpages'][0];

        // Handle user creation
        if ($subpage === 'create') {
            // Validate form fields
            if (
                !FormController::validate('username', ['maxLength' => 100]) ||
                !FormController::validate('first_name', ['maxLength' => 100]) ||
                !FormController::validate('last_name', ['maxLength' => 100]) ||
                !FormController::validate('email', ['required', 'maxLength' => 100, 'type' => 'email']) ||
                !FormController::validate('role', ['required'])
            ) return;

            // Sanitize inputs
            FormController::sanitizeFields(['username', 'first_name', 'last_name', 'email']);

            // Check if email already exists
            if (AuthController::checkEmail($_POST['email'])) {
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
                $_POST['last_name']
            );
        }

        // Handle user editing
        if ($subpage === 'edit') {
            // Validate form fields
            if (
                !FormController::validate('username', ['maxLength' => 100]) ||
                !FormController::validate('first_name', ['maxLength' => 100]) ||
                !FormController::validate('last_name', ['maxLength' => 100]) ||
                !FormController::validate('email', ['required', 'maxLength' => 100, 'type' => 'email']) ||
                !FormController::validate('role', ['required'])
            ) return;

            // Sanitize inputs
            FormController::sanitizeFields(['username', 'first_name', 'last_name', 'email']);

            // Check if email already in use by another user
            if (AuthController::checkEmail($_POST['email']) && AuthController::getUserIdByEmail($_POST['email']) !== $this->user['id']) {
                $_POST['email'] = $this->user['email'];
                FormController::addAlert('An account with this email already exists!', AlertType::WARNING);
                return;
            }

            // Update user
            self::update(
                $this->user['id'],
                $_POST['username'],
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['email'],
                $_POST['role']
            );
        }

        // Handle user deletion
        if ($subpage === 'delete') $this->delete($this->user['id']);

        // Handle user restoration
        if ($subpage === 'restore') {
            // Only restore if user is actually inactive
            if (isset($this->user) && !$this->user['is_active']) $this->restore($this->user['id']);
            else PageController::redirect('users', 2);
        }
    }

    /**
     * Creates a new user in the system by inserting user details into the database, assigning a role,
     * sending an account creation email, and redirecting to the users page with a success message.
     *
     * @param string      $email The email address of the new user.
     * @param string      $rawPassword The raw password for the new user, which will be hashed before storing.
     * @param string      $role The role name to assign to the new user.
     * @param string|null $username An optional username for the new user. Default is null.
     * @param string|null $first_name An optional first name for the new user. Default is null.
     * @param string|null $last_name An optional last name for the new user. Default is null.
     *
     * @return void
     */
    private static function create(string $email, string $rawPassword, string $role, string|null $username = null, string|null $first_name = null, string|null $last_name = null): void
    {
        // Insert new user
        DB::insert(
            'users',
            [
                'username' => $username ?? null,
                'first_name' => $first_name ?? null,
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
                'role_id' => DB::single(
                    'id',
                    'roles',
                    [
                        'name' => $role
                    ]
                )['id']
            ]
        );

        // Send account creation email
        AuthController::sendCreatedUserMail($email, $rawPassword);

        // Redirect with success message
        PageController::redirect('users');
        AlertController::globalAlert('Success! The user has been created!', AlertType::SUCCESS, 4);
    }

    /**
     * Updates a user's profile information, role, and redirects with a success message.
     *
     * @param int         $id The ID of the user to update.
     * @param string|null $username The updated username, or null to leave unchanged.
     * @param string|null $first_name The updated first name, or null to leave unchanged.
     * @param string|null $last_name The updated last name, or null to leave unchanged.
     * @param string      $email The updated email address.
     * @param string      $role The updated role name to assign to the user.
     *
     * @return void
     */
    private static function update(int $id, string|null $username, string|null $first_name, string|null $last_name, string $email, string $role): void
    {
        // Update user profile
        DB::update(
            'users',
            [
                'username' => $username ?? null,
                'first_name' => $first_name ?? null,
                'last_name' => $last_name ?? null,
                'email' => $email
            ],
            compact('id')
        );

        // Update user role
        DB::update(
            'user_roles',
            [
                'role_id' => DB::single(
                    'id',
                    'roles',
                    [
                        'name' => $role
                    ]
                )['id']
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
     * Performs a soft delete on a user by marking them as inactive and setting the deletion timestamp.
     * Also redirects to the users page and displays a success alert message.
     *
     * @param int $id The unique identifier of the user to be deleted.
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
     * Restores a user by reactivating their status and clearing their deletion timestamp.
     *
     * @param int $id The ID of the user to restore.
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

    /**
     * Outputs a JSON-encoded response containing table structure,
     * table data, pagination details, informational messages,
     * and the total number of users.
     *
     * @return void
     * @throws JsonException
     *
     * @api
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
     * Renders the table header row as an HTML string for the API response.
     *
     * @return string The generated HTML string for the table header row.
     */
    private function renderThead(): string
    {
        $html = '<tr>';

        foreach ($this->tableColumns as $column) {
            $sortParams = array_merge($this->activeFilterParams, $this->getNextSortParams($column));
            $href = '/users' . ($sortParams ? '?' . http_build_query($sortParams) : '');
            $widthAttr = $column['width'] ? ' data-width="' . $column['width'] . '"' : '';
            $sortClass = !empty($column['sortable']) ? 'sortable ' . $this->getColumnSortClass($column) : '';

            $html .= '<th class="' . $sortClass . '"' . $widthAttr . '>';
            $html .= !empty($column['sortable']) ? '<a class="table-sort-link" href="' . $href . '">' . htmlspecialchars($column['label']) . '</a>' : '<p class="table-header-label">' . htmlspecialchars($column['label']) . '</p>';
            $html .= '</th>';
        }

        return $html . '</tr>';
    }

    /**
     * Generates the next set of sorting parameters based on the provided column configuration.
     *
     * @param array $column The column configuration array.
     *
     * @return array An associative array containing the next sorting parameters, or an empty array.
     */
    public function getNextSortParams(array $column): array
    {
        if (empty($column['sortable'])) return [];
        if ($this->sortColumn !== $column['key']) return ['sort' => $column['key'], 'dir' => self::SORT_ASC];
        if ($this->sortDirection === self::SORT_ASC) return ['sort' => $column['key'], 'dir' => self::SORT_DESC];
        return [];
    }

    /**
     * Determines the CSS class for column sorting based on sortability and current sort direction.
     *
     * @param array $column An associative array representing column data.
     *
     * @return string Returns 'sort-asc', 'sort-desc', or an empty string.
     */
    public function getColumnSortClass(array $column): string
    {
        if (empty($column['sortable']) || $this->sortColumn !== $column['key']) return '';
        return $this->sortDirection === self::SORT_ASC ? 'sort-asc' : 'sort-desc';
    }

    /**
     * Generates the HTML markup for the table body for the API response.
     *
     * @return string The generated HTML string for the table body content.
     */
    private function renderTbody(): string
    {
        $html = '';

        foreach ($this->pagedUsers as $user) {
            $html .= '<tr class="' . (!$user['is_active'] ? 'deleted' : '') . '">';

            foreach ($this->tableColumns as $column) {
                if ($column['key'] === 'actions') {
                    if ($user['id'] !== $this->currentUserId) {
                        $html .= '<td class="table-actions"><div class="row g-col-0.5 center-y">';
                        $html .= $user['is_active']
                            ? '<a class="col table-action f-0" href="/users/edit?id=' . $user['id'] . '"><i class="fas fa-pen"></i></a><a class="col table-action delete f-0" href="/users/delete?id=' . $user['id'] . '"><i class="fas fa-trash"></i></a>'
                            : '<a class="col table-action restore f-0" href="/users/restore?id=' . $user['id'] . '"><i class="fas fa-wrench"></i></a>';
                        $html .= '</div></td>';
                    } else $html .= '<td><span class="text-muted">-</span></td>';
                } else $html .= '<td>' . $this->renderCell($column, $user) . '</td>';
            }

            $html .= '</tr>';
        }

        return $html;
    }

    /**
     * Renders the content of a table cell based on a given column configuration and user data.
     *
     * @param array $column An associative array containing the column configuration.
     * @param array $user An associative array containing user data.
     *
     * @return string The HTML string for the cell content.
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
            'role' => $user['role']->value,
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
     * Renders the pagination navigation links for the API response.
     *
     * @return string The HTML string containing the pagination links.
     */
    private function renderPagination(): string
    {
        $cls = 'class="col link lh-1 g-col-0.5 center f-0"';
        $prev = '/users?' . http_build_query(['page' => $this->page - 1] + $this->activeQueryParams);
        $next = '/users?' . http_build_query(['page' => $this->page + 1] + $this->activeQueryParams);

        return '<a ' . $cls . ' href="' . $prev . '" ' . ($this->page > 0 ? '' : 'inert') . '><i class="fas fa-chevron-left"></i>Previous</a>' . '<a ' . $cls . ' href="' . $next . '" ' . ($this->page < $this->maxPage ? '' : 'inert') . '>Next<i class="fas fa-chevron-right"></i></a>';
    }

    /**
     * Returns the pagination info string.
     *
     * @return string
     */
    private function renderPaginationInfo(): string
    {
        return $this->startIndex . ' - ' . $this->endIndex . ' of ' . $this->totalUsers . ($this->search !== '' ? ' / ' . $this->totalAllUsers : '') . ' users';
    }
}
