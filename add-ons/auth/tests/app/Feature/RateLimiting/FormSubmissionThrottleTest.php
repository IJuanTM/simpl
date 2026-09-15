<?php

declare(strict_types=1);

namespace tests\Feature\RateLimiting;

use app\Controllers\FormController;
use app\Pages\Traits\RateLimitedForm;
use app\Utils\RateLimiter;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

final class FormSubmissionThrottleHost
{
    use RateLimitedForm;
}

final class FormSubmissionThrottleTest extends TestCase
{
    private array $keys = [];

    public function testGetRequestIsNotTreatedAsASubmission(): void
    {
        // Arrange
        $host = new FormSubmissionThrottleHost();
        $prefix = 'contact-' . uniqid('', true);

        // Act + Assert
        $this->assertFalse($this->call($host, 'initRateLimitedForm', [$prefix]));
        $this->rlKey($host);
    }

    private function call(FormSubmissionThrottleHost $host, string $method, array $args = []): mixed
    {
        return (new ReflectionMethod(FormSubmissionThrottleHost::class, $method))->invoke($host, ...$args);
    }

    private function rlKey(FormSubmissionThrottleHost $host): void
    {
        $this->keys[] = new ReflectionProperty(FormSubmissionThrottleHost::class, 'rlKey')->getValue($host);
    }

    public function testPostWithSubmitFlagIsTreatedAsASubmission(): void
    {
        // Arrange
        $host = new FormSubmissionThrottleHost();
        $prefix = 'contact-' . uniqid('', true);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['submit'] = '1';

        // Act + Assert
        $this->assertTrue($this->call($host, 'initRateLimitedForm', [$prefix]));
        $this->rlKey($host);
    }

    public function testNonSubmittingRequestReadsAnExistingCooldown(): void
    {
        // Arrange
        $prefix = 'contact-' . uniqid('', true);
        $submitting = new FormSubmissionThrottleHost();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['submit'] = '1';
        $this->call($submitting, 'initRateLimitedForm', [$prefix]);
        $this->rlKey($submitting);
        $this->call($submitting, 'attemptRateLimit', [60]);

        // Act
        $viewer = new FormSubmissionThrottleHost();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_POST['submit']);
        $this->call($viewer, 'initRateLimitedForm', [$prefix]);

        // Assert
        $this->assertGreaterThan(0, $viewer->cooldown);
    }

    public function testFirstAttemptWithinTheWindowIsAllowed(): void
    {
        // Arrange
        $host = $this->newSubmittedHost();

        // Act + Assert
        $this->assertTrue($this->call($host, 'attemptRateLimit', [60]));
    }

    private function newSubmittedHost(): FormSubmissionThrottleHost
    {
        $host = new FormSubmissionThrottleHost();
        $this->call($host, 'initRateLimitedForm', ['contact-' . uniqid('', true)]);
        $this->rlKey($host);
        return $host;
    }

    public function testSecondAttemptWithinTheWindowIsBlockedAndSetsCooldown(): void
    {
        // Arrange
        $host = $this->newSubmittedHost();

        // Act
        $this->call($host, 'attemptRateLimit', [60]);

        // Assert
        $this->assertFalse($this->call($host, 'attemptRateLimit', [60]));
        $this->assertGreaterThan(0, $host->cooldown);
    }

    public function testBlockedAttemptQueuesAWarningAlert(): void
    {
        // Arrange
        $host = $this->newSubmittedHost();

        // Act
        $this->call($host, 'attemptRateLimit', [60]);
        $this->call($host, 'attemptRateLimit', [60]);

        // Assert
        $this->assertStringContainsString('Too many attempts', FormController::formAlerts());
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
