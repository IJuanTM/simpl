<?php

declare(strict_types=1);

namespace tests\Pages\Traits;

use app\Controllers\FormController;
use app\Pages\Traits\RateLimitedForm;
use app\Utils\RateLimiter;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

class RateLimitedFormHost
{
    use RateLimitedForm;
}

class RateLimitedFormTest extends TestCase
{
    private array $keys = [];

    public function testGetRequestIsNotTreatedAsASubmission(): void
    {
        $host = new RateLimitedFormHost();
        $prefix = 'contact-' . uniqid();

        $this->assertFalse($this->init($host, $prefix));
        $this->rlKey($host);
    }

    private function init(RateLimitedFormHost $host, string $prefix): bool
    {
        return (new ReflectionMethod(RateLimitedFormHost::class, 'initRateLimitedForm'))->invoke($host, $prefix);
    }

    private function rlKey(RateLimitedFormHost $host): string
    {
        $key = (new ReflectionProperty(RateLimitedFormHost::class, 'rlKey'))->getValue($host);
        $this->keys[] = $key;
        return $key;
    }

    public function testPostWithSubmitFlagIsTreatedAsASubmission(): void
    {
        $host = new RateLimitedFormHost();
        $prefix = 'contact-' . uniqid();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['submit'] = '1';

        $this->assertTrue($this->init($host, $prefix));
        $this->rlKey($host);
    }

    public function testFirstAttemptWithinTheWindowIsAllowed(): void
    {
        $host = new RateLimitedFormHost();
        $prefix = 'contact-' . uniqid();
        $this->init($host, $prefix);
        $this->rlKey($host);

        $this->assertTrue($this->attemptRateLimit($host, 60));
    }

    private function attemptRateLimit(RateLimitedFormHost $host, int $windowSeconds): bool
    {
        return (new ReflectionMethod(RateLimitedFormHost::class, 'attemptRateLimit'))->invoke($host, $windowSeconds);
    }

    public function testSecondAttemptWithinTheWindowIsBlockedAndSetsCooldown(): void
    {
        $host = new RateLimitedFormHost();
        $prefix = 'contact-' . uniqid();
        $this->init($host, $prefix);
        $this->rlKey($host);

        $this->attemptRateLimit($host, 60);
        $this->assertFalse($this->attemptRateLimit($host, 60));
        $this->assertGreaterThan(0, $host->cooldown);
    }

    public function testBlockedAttemptQueuesAWarningAlert(): void
    {
        $host = new RateLimitedFormHost();
        $prefix = 'contact-' . uniqid();
        $this->init($host, $prefix);
        $this->rlKey($host);

        $this->attemptRateLimit($host, 60);
        $this->attemptRateLimit($host, 60);

        $this->assertNotNull(FormController::formAlerts());
    }

    public function testNonSubmittingRequestReadsAnExistingCooldown(): void
    {
        $prefix = 'contact-' . uniqid();

        $submitting = new RateLimitedFormHost();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['submit'] = '1';
        $this->init($submitting, $prefix);
        $this->rlKey($submitting);
        $this->attemptRateLimit($submitting, 60);
        $this->attemptRateLimit($submitting, 60);

        $viewer = new RateLimitedFormHost();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_POST['submit']);
        $this->init($viewer, $prefix);

        $this->assertGreaterThan(0, $viewer->cooldown);
    }

    protected function setUp(): void
    {
        unset($_POST['submit']);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        FormController::$alerts = [];
    }

    protected function tearDown(): void
    {
        foreach ($this->keys as $key) RateLimiter::clear($key);
    }
}
