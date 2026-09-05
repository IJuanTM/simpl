<?php

declare(strict_types=1);

namespace app\Pages\Admin;

use app\Controllers\AppController;
use app\Database\DB;
use app\Models\Page;
use app\Pages\Admin\Traits\AdminTableTrait;

/**
 * Admin page: displays a paginated, filterable log of login attempts with user and IP context.
 */
class LoginAttempts
{
    use AdminTableTrait;

    private const array PER_PAGE_OPTIONS = [25, 50, 100];

    public array $attempts = [];
    public int $perPage = 25;
    public array $perPageOptions = self::PER_PAGE_OPTIONS;

    public function __construct(Page $page)
    {
        $this->tableColumns = self::getTableColumns();
        $this->itemLabel = 'attempts';
        $this->filterDefinitions = ['status' => ['success', 'failed']];

        $this->initTable($page);
        $this->loadAttempts();
    }

    /**
     * Returns the table column definitions for the login attempts table.
     *
     * @return array<int, array{key: string, label: string, sortable: bool, width: int|null, visible: bool}>
     */
    private static function getTableColumns(): array
    {
        return self::buildColumns([
            ['id', '#', false, 64, true],
            ['user', 'User', false, 160, true],
            ['ip_address', 'IP address', false, 160, true],
            ['user_agent', 'User agent', false, 288, true],
            ['attempt_time', 'Time', false, 160, true],
            ['status', 'Status', false, 128, true],
        ]);
    }

    /**
     * Loads the current page of login attempts matching the active search/filter.
     */
    private function loadAttempts(): void
    {
        $where = [];
        if ($this->filters['status'] === 'success') $where['login_attempts.success'] = 1;
        else if ($this->filters['status'] === 'failed') $where['login_attempts.success'] = 0;

        $orWhere = [];
        if ($this->search !== '') {
            // Escape LIKE wildcards so a literal '%' or '_' in the search term isn't treated as a pattern
            $escapedSearch = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $this->search);
            $like = ['LIKE', '%' . $escapedSearch . '%'];
            $orWhere = [
                'login_attempts.ip_address' => $like,
                'users.username' => $like,
                'users.email' => $like,
            ];
        }

        $total = DB::count(
            FROM: 'login_attempts',
            JOIN: ['user_id', ['users', 'id']],
            WHERE: $where,
            OR_WHERE: $orWhere
        );

        $offset = $this->applyPagination($total);

        $rows = DB::select(
            SELECT: [
                'login_attempts.id',
                'login_attempts.ip_address',
                'login_attempts.user_agent',
                'login_attempts.attempt_time',
                'login_attempts.success',
                'login_attempts.failed_reason',
                'users.username',
                'users.email',
            ],
            FROM: 'login_attempts',
            JOIN: ['user_id', ['users', 'id']],
            WHERE: $where,
            OR_WHERE: $orWhere,
            ORDER_BY: 'login_attempts.attempt_time DESC',
            LIMIT: $this->perPage,
            OFFSET: $offset
        );

        foreach ($rows as $row) {
            $row['ip_address'] = AppController::sanitize($row['ip_address'] ?? '');
            $row['user_agent'] = AppController::sanitize($row['user_agent'] ?? '');
            $row['username'] = AppController::sanitize($row['username'] ?? '');
            $row['email'] = AppController::sanitize($row['email'] ?? '');
            $row['failed_reason'] = AppController::sanitize($row['failed_reason'] ?? '');
            $this->attempts[] = $row;
        }
    }

    /**
     * Renders an attempt row's cell for the given column.
     */
    public function renderCell(array $column, array $row): string
    {
        return match ($column['key']) {
            'id' => (string)$row['id'],
            'user' => self::renderUserCell($row),
            'ip_address' => $row['ip_address'],
            'user_agent' => '<span title="' . $row['user_agent'] . '">' . $row['user_agent'] . '</span>',
            'attempt_time' => $row['attempt_time'],
            'status' => $this->renderBadge((bool)$row['success'], $row['success'] ? 'Success' : ($row['failed_reason'] ? ucfirst($row['failed_reason']) : 'Failed')),
            default => '',
        };
    }

    /**
     * Renders the 'user' column cell, preferring username over email when both are present.
     * Uses explicit null/empty checks (not truthy checks) so a username of "0" isn't treated as absent.
     */
    private static function renderUserCell(array $row): string
    {
        $username = $row['username'] ?? '';
        $email = $row['email'] ?? '';

        if ($username === '' && $email === '') return '<span class="text-muted">Unknown</span>';

        $primary = $username !== '' ? $username : $email;
        $secondary = $username !== '' && $email !== '' ? '<br><small>' . $email . '</small>' : '';

        return '<span>' . $primary . '</span>' . $secondary;
    }

    /**
     * Base route for pagination/sort-link hrefs.
     */
    private function routePath(): string
    {
        return '/admin/login-attempts';
    }

    /**
     * Row data for the current page.
     */
    private function pageRows(): array
    {
        return $this->attempts;
    }
}
