<?php

declare(strict_types=1);

namespace app\Pages\Admin;

use app\Controllers\AppController;
use app\Database\DB;
use app\Models\Page;

/**
 * LoginAttempts (admin)
 *
 * Displays a paginated, filterable log of login attempts with user and IP context.
 */
class LoginAttempts
{
    private const array PER_PAGE_OPTIONS = [25, 50, 100];

    public array $attempts = [];
    public int $total = 0;
    public int $page = 0;
    public int $perPage = 25;
    public array $perPageOptions = self::PER_PAGE_OPTIONS;
    public int $maxPage = 0;
    public int $startIndex = 0;
    public int $endIndex = 0;
    public string $filterStatus = '';
    public string $search = '';
    public array $activeQueryParams = [];

    public function __construct(Page $page)
    {
        if ($page->param('page') !== null) $this->page = max(0, (int)$page->param('page'));
        if ($page->param('per_page') !== null && in_array((int)$page->param('per_page'), self::PER_PAGE_OPTIONS, true)) $this->perPage = (int)$page->param('per_page');
        if ($page->param('status') !== null && in_array($page->param('status'), ['success', 'failed'], true)) $this->filterStatus = (string)$page->param('status');
        if ($page->param('search') !== null) $this->search = trim((string)$page->param('search'));

        $this->activeQueryParams = array_filter([
            'status' => $this->filterStatus ?: null,
            'search' => $this->search ?: null,
            'per_page' => $this->perPage !== 25 ? $this->perPage : null,
        ]);

        $this->loadAttempts();
    }

    private function loadAttempts(): void
    {
        [$where, $params] = $this->buildWhere();

        $this->total = (int)(DB::query(
            "SELECT COUNT(*) AS count FROM login_attempts la LEFT JOIN users u ON la.user_id = u.id $where",
            $params
        )[0]['count'] ?? 0);

        $this->maxPage = $this->total > 0 ? (int)ceil($this->total / $this->perPage) - 1 : 0;
        $this->page = min($this->page, $this->maxPage);
        $offset = $this->page * $this->perPage;
        $this->startIndex = $this->total > 0 ? $offset + 1 : 0;
        $this->endIndex = min($offset + $this->perPage, $this->total);

        $rows = DB::query(
            "SELECT la.id, la.ip_address, la.user_agent, la.attempt_time, la.success, la.failed_reason,
                    u.username, u.email
             FROM login_attempts la
             LEFT JOIN users u ON la.user_id = u.id
             $where
             ORDER BY la.attempt_time DESC
             LIMIT :limit OFFSET :offset",
            array_merge($params, [':limit' => $this->perPage, ':offset' => $offset])
        );

        foreach ($rows as $row) {
            $row['ip_address'] = AppController::sanitize($row['ip_address'] ?? '');
            $row['user_agent'] = AppController::sanitize($row['user_agent'] ?? '');
            $row['username'] = AppController::sanitize($row['username'] ?? '');
            $row['email'] = AppController::sanitize($row['email'] ?? '');
            $this->attempts[] = $row;
        }
    }

    private function buildWhere(): array
    {
        $conditions = [];
        $params = [];

        if ($this->filterStatus === 'success') $conditions[] = 'la.success = 1';
        else if ($this->filterStatus === 'failed') $conditions[] = 'la.success = 0';

        if ($this->search !== '') {
            $conditions[] = '(la.ip_address LIKE :search OR u.username LIKE :search OR u.email LIKE :search)';
            $params[':search'] = '%' . $this->search . '%';
        }

        return [$conditions ? 'WHERE ' . implode(' AND ', $conditions) : '', $params];
    }
}
