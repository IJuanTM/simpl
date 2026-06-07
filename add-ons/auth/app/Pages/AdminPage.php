<?php

declare(strict_types=1);

namespace app\Pages;

use app\Controllers\AuthController;
use app\Controllers\PageController;
use app\Enums\Role;
use app\Models\Page;
use app\Pages\Admin\LoginAttempts;
use app\Pages\Admin\Roles;
use app\Pages\Admin\Users;

class AdminPage
{
    public string $section;
    public ?string $subAction;
    private Users|Roles|LoginAttempts|null $delegate = null;

    public function __construct(Page $page)
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
    }

    public function __get(string $name): mixed
    {
        return $this->delegate?->$name;
    }

    public function __set(string $name, mixed $value): void
    {
        if ($this->delegate !== null) $this->delegate->$name = $value;
    }

    public function __isset(string $name): bool
    {
        return isset($this->delegate->$name);
    }

    public function __call(string $name, array $args): mixed
    {
        return $this->delegate?->$name(...$args);
    }

    final public function api(): void
    {
        $this->delegate?->api();
    }
}
