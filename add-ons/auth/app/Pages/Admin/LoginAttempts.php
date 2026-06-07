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
    public int $total = 0;
    public int $perPage = 25;
    public array $perPageOptions = self::PER_PAGE_OPTIONS;
    public string $filterStatus = '';

    public function __construct(Page $page)
    {
        $this->readTableParams($page);

        if ($page->param('status') !== null && in_array($page->param('status'), ['success', 'failed'], true)) $this->filterStatus = (string)$page->param('status');

        $this->activeQueryParams = array_filter([
            'status' => $this->filterStatus ?: null,
            'search' => $this->search ?: null,
            'per_page' => $this->perPage !== 25 ? $this->perPage : null,
        ]);

        $this->loadAttempts();
    }

    private function loadAttempts(): void
    {
        $where = [];
        if ($this->filterStatus === 'success') $where['login_attempts.success'] = 1;
        else if ($this->filterStatus === 'failed') $where['login_attempts.success'] = 0;

        $orWhere = [];
        if ($this->search !== '') {
            $like = ['LIKE', '%' . $this->search . '%'];
            $orWhere = [
                'login_attempts.ip_address' => $like,
                'users.username' => $like,
                'users.email' => $like,
            ];
        }

        $this->total = DB::count(
            FROM: 'login_attempts',
            JOIN: ['user_id', ['users', 'id']],
            WHERE: $where,
            OR_WHERE: $orWhere
        );

        $offset = $this->applyPagination($this->total);

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
}
