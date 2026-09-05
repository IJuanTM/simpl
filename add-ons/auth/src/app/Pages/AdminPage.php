<?php

declare(strict_types=1);

namespace app\Pages;

use app\Controllers\AuthController;
use app\Controllers\BreadcrumbController;
use app\Controllers\PageController;
use app\Enums\Role;
use app\Models\Page;
use app\Pages\Admin\LoginAttempts;
use app\Pages\Admin\Roles;
use app\Pages\Admin\Users;

/**
 * Dispatches /admin/{section} requests to the matching Admin\{Users,Roles,LoginAttempts} page.
 * Forwards property/method access to that delegate via magic methods, so views can call $page->whatever without knowing which concrete admin page is active.
 */
class AdminPage
{
    public string $section;
    public ?string $subAction;
    private Users|Roles|LoginAttempts|null $delegate = null;

    public function __construct(Page $page)
    {
        $this->dispatch($page);
    }

    /**
     * Requires an admin role, resolves the section's delegate page object, and generates breadcrumbs.
     *
     * @param Page $page
     *
     * @return void
     */
    private function dispatch(Page $page): void
    {
        AuthController::requireAuth([Role::ADMIN]);

        $this->section = $page->subpage(0) ?? 'dashboard';
        $this->subAction = $page->subpage(1);

        $this->delegate = match ($this->section) {
            'users' => new Users($page),
            'roles' => new Roles($page),
            'login-attempts' => new LoginAttempts($page),
            default => null,
        };

        if ($this->delegate === null && $this->section !== 'dashboard') PageController::redirect('admin/users');

        BreadcrumbController::generate($page);
    }

    /**
     * Forwards property reads to the active delegate page.
     */
    public function __get(string $name): mixed
    {
        return $this->delegate?->$name;
    }

    /**
     * Forwards property writes to the active delegate page.
     */
    public function __set(string $name, mixed $value): void
    {
        if ($this->delegate !== null) $this->delegate->$name = $value;
    }

    /**
     * Forwards isset() checks to the active delegate page.
     */
    public function __isset(string $name): bool
    {
        return isset($this->delegate->$name);
    }

    /**
     * Forwards method calls to the active delegate page.
     */
    public function __call(string $name, array $args): mixed
    {
        return $this->delegate?->$name(...$args);
    }

    /**
     * Forwards API requests to the active delegate page.
     */
    final public function api(): void
    {
        $this->delegate?->api();
    }
}
