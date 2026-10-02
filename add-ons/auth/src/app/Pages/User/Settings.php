<?php

declare(strict_types=1);

namespace app\Pages\User;

use app\Controllers\AuthController;
use app\Controllers\BreadcrumbController;
use app\Controllers\PageController;
use app\Enums\ErrorCode;
use app\Models\Page;

/**
 * Dispatches /user/settings/{section} requests to the matching section delegate.
 * Forwards property/method access to that delegate via magic methods, so views can reach section state through $this->pageObj->settings without knowing which section is active.
 */
class Settings
{
    public string $section;
    private ProfileSettings|PasswordSettings|TwoFactorSettings|null $delegate = null;

    public function __construct(Page $page)
    {
        $this->dispatch($page);
    }

    /**
     * Requires an authenticated user and resolves the section's delegate.
     *
     * @param Page $page
     *
     * @return void
     */
    private function dispatch(Page $page): void
    {
        $this->section = $page->subpage(1) ?? 'overview';

        AuthController::requireAuth();

        $this->delegate = match ($this->section) {
            'profile' => new ProfileSettings(),
            'change-password' => new PasswordSettings(),
            'two-factor' => new TwoFactorSettings(),
            default => null,
        };

        if ($this->delegate === null && $this->section !== 'overview') {
            PageController::redirect('user/settings');
            return;
        }

        BreadcrumbController::set($this->breadcrumbTrail());
    }

    /**
     * The breadcrumb trail for the active section, rooted at the settings hub.
     * Built by hand rather than BreadcrumbController::generate(), whose auto "User" root crumb would link to /user, which 404s without an id.
     *
     * @return array<int, array{label: string, url: string|null}>
     */
    private function breadcrumbTrail(): array
    {
        $root = ['label' => 'Settings', 'url' => 'user/settings'];

        return match ($this->section) {
            'profile' => [$root, ['label' => 'Profile', 'url' => null]],
            'change-password' => [$root, ['label' => 'Change password', 'url' => null]],
            'two-factor' => [$root, ['label' => 'Two-factor', 'url' => null]],
            default => [['label' => 'Settings', 'url' => null]],
        };
    }

    /**
     * Forwards property reads to the active section delegate.
     */
    public function __get(string $name): mixed
    {
        return $this->delegate?->$name;
    }

    /**
     * Forwards property writes to the active section delegate.
     */
    public function __set(string $name, mixed $value): void
    {
        if ($this->delegate !== null) $this->delegate->$name = $value;
    }

    /**
     * Forwards isset() checks to the active section delegate.
     */
    public function __isset(string $name): bool
    {
        return isset($this->delegate->$name);
    }

    /**
     * Forwards method calls to the active section delegate.
     */
    public function __call(string $name, array $args): mixed
    {
        return $this->delegate?->$name(...$args);
    }

    /**
     * Forwards API requests to the active section delegate.
     *
     * @param Page $page
     *
     * @return void
     */
    final public function api(Page $page): void
    {
        if ($this->delegate === null) {
            PageController::error(ErrorCode::NOT_FOUND);
            return;
        }

        $this->delegate->api($page);
    }
}
