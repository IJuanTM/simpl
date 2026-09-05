<?php

declare(strict_types=1);

namespace tests\Pages\Traits;

use app\Controllers\FormController;
use app\Pages\Traits\RateLimitedForm;
use app\Utils\RateLimiter;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

final class RateLimitedFormHost
{
    use RateLimitedForm;
}

final class RateLimitedFormTest extends TestCase
{
    private array $keys = [];

    public function testGetRequestIsNotTreatedAsASubmission(): void
    {
        // Arrange
        $host = new RateLimitedFormHost();
        $prefix = 'contact-' . uniqid('', true);

        // Act + Assert
        $this->assertFalse($this->init($host, $prefix));
        $this->rlKey($host);
    }

    private function init(RateLimitedFormHost $host, string $prefix): bool
    {
        return new ReflectionMethod(RateLimitedFormHost::class, 'initRateLimitedForm')->invoke($host, $prefix);
    }

    private function rlKey(RateLimitedFormHost $host): void
    {
        $this->keys[] = new ReflectionProperty(RateLimitedFormHost::class, 'rlKey')->getValue($host);
    }

    public function testPostWithSubmitFlagIsTreatedAsASubmission(): void
    {
        // Arrange
        $host = new RateLimitedFormHost();
        $prefix = 'contact-' . uniqid('', true);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['submit'] = '1';

        // Act + Assert
        $this->assertTrue($this->init($host, $prefix));
        $this->rlKey($host);
    }

    public function testNonSubmittingRequestReadsAnExistingCooldown(): void
    {
        // Arrange
        $prefix = 'contact-' . uniqid('', true);
        $submitting = new RateLimitedFormHost();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['submit'] = '1';
        $this->init($submitting, $prefix);
        $this->rlKey($submitting);
        $this->attemptRateLimit($submitting, 60);
        $this->attemptRateLimit($submitting, 60);

        // Act
        $viewer = new RateLimitedFormHost();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_POST['submit']);
        $this->init($viewer, $prefix);

        // Assert
        $this->assertGreaterThan(0, $viewer->cooldown);
    }

    private function attemptRateLimit(RateLimitedFormHost $host, int $windowSeconds): bool
    {
        return (new ReflectionMethod(RateLimitedFormHost::class, 'attemptRateLimit'))->invoke($host, $windowSeconds);
    }

    public function testFirstAttemptWithinTheWindowIsAllowed(): void
    {
        // Arrange
        $host = $this->newSubmittedHost();

        // Act + Assert
        $this->assertTrue($this->attemptRateLimit($host, 60));
    }

    private function newSubmittedHost(): RateLimitedFormHost
    {
        $host = new RateLimitedFormHost();
        $this->init($host, 'contact-' . uniqid('', true));
        $this->rlKey($host);
        return $host;
    }

    public function testSecondAttemptWithinTheWindowIsBlockedAndSetsCooldown(): void
    {
        // Arrange
        $host = $this->newSubmittedHost();

        // Act
        $this->attemptRateLimit($host, 60);

        // Assert
        $this->assertFalse($this->attemptRateLimit($host, 60));
        $this->assertGreaterThan(0, $host->cooldown);
    }

    public function testBlockedAttemptQueuesAWarningAlert(): void
    {
        // Arrange
        $host = $this->newSubmittedHost();

        // Act
        $this->attemptRateLimit($host, 60);
        $this->attemptRateLimit($host, 60);

        // Assert
        $this->assertNotNull(FormController::formAlerts());
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
