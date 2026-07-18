<?php

declare(strict_types=1);

namespace app\Pages\Admin;

use app\Controllers\AppController;
use app\Database\DB;
use app\Models\Page;
use app\Pages\Admin\Traits\AdminTableTrait;

/**
 * LoginAttempts (admin)
 *
 * Displays a paginated, filterable log of login attempts with user and IP context.
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
        return array_map(static fn(array $c): array => ['key' => $c[0], 'label' => $c[1], 'sortable' => $c[2], 'width' => $c[3], 'visible' => $c[4]], [
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
            $like = ['LIKE', '%' . $this->search . '%'];
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
            'user' => match (true) {
                $row['username'] || $row['email'] => '<span>' . ($row['username'] ?: $row['email']) . '</span>' . ($row['username'] && $row['email'] ? '<br><small>' . $row['email'] . '</small>' : ''),
                default => '<span class="text-muted">Unknown</span>',
            },
            'ip_address' => $row['ip_address'],
            'user_agent' => '<span title="' . $row['user_agent'] . '">' . $row['user_agent'] . '</span>',
            'attempt_time' => $row['attempt_time'],
            'status' => $row['success'] ? '<span class="badge badge-success">Success</span>' : '<span class="badge badge-error">' . (ucfirst($row['failed_reason']) ?? 'Failed') . '</span>',
            default => '',
        };
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
