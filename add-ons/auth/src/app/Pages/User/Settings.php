<?php

declare(strict_types=1);

namespace app\Pages\User;

use app\Controllers\AuthController;
use app\Controllers\PageController;
use app\Models\Page;

/**
 * Dispatches /user/settings/{section} requests to the matching section delegate.
 * Forwards property/method access to that delegate via magic methods, so views can reach section state through $this->pageObj->settings without knowing which section is active.
 */
class Settings
{
    public string $section;
    private ?SecuritySettings $delegate = null;

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
        // The security section is where a forced password change is resolved, so allow it through.
        AuthController::requireAuth(null, true);

        $this->section = $page->subpage(1) ?? 'overview';

        $this->delegate = match ($this->section) {
            'security' => new SecuritySettings(),
            default => null,
        };

        if ($this->delegate === null && $this->section !== 'overview') PageController::redirect('user/settings');
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
}
