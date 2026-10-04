<?php

declare(strict_types=1);

namespace tests\Feature\RateLimiting;

use app\Controllers\FormController;
use app\Pages\Traits\TwoTierThrottle;
use app\Utils\RateLimiter;
use PHPUnit\Framework\TestCase;

final class TwoTierThrottleHost
{
    use TwoTierThrottle;

    public function attempt(string $accountKey, int $accountMax, string $ipKeyPrefix, int $ipMax): bool
    {
        return $this->twoTierThrottle(
            $accountKey,
            $accountMax,
            60,
            10,
            100,
            'Too many attempts on this account. Please wait before trying again.',
            $ipKeyPrefix,
            $ipMax,
            60,
            'Too many attempts from this network. Please wait before trying again.',
        );
    }
}

final class TwoTierThrottleTest extends TestCase
{
    private array $accountKeys = [];
    private array $ipKeyPrefixes = [];

    public function testNeitherTierHitReturnsFalseAndQueuesNoAlert(): void
    {
        // Arrange
        $host = new TwoTierThrottleHost();

        // Act
        $blocked = $host->attempt($this->accountKey(), 5, $this->ipKeyPrefix(), 5);

        // Assert
        $this->assertFalse($blocked);
        $this->assertNull(FormController::formAlerts());
    }

    private function accountKey(): string
    {
        $key = 'account-' . bin2hex(random_bytes(8));
        $this->accountKeys[] = $key;
        return $key;
    }

    private function ipKeyPrefix(): string
    {
        $prefix = 'ip-' . bin2hex(random_bytes(8));
        $this->ipKeyPrefixes[] = $prefix;
        return $prefix;
    }

    public function testAccountTierHitReturnsTrueAndQueuesTheAccountAlert(): void
    {
        // Arrange
        $host = new TwoTierThrottleHost();
        $accountKey = $this->accountKey();
        $ipKeyPrefix = $this->ipKeyPrefix();
        $host->attempt($accountKey, 1, $ipKeyPrefix, 5);

        // Act
        $blocked = $host->attempt($accountKey, 1, $ipKeyPrefix, 5);

        // Assert
        $this->assertTrue($blocked);
        $this->assertStringContainsString('this account', FormController::formAlerts());
    }

    public function testIpTierHitReturnsTrueAndQueuesTheIpAlertWithoutTouchingTheAccountTier(): void
    {
        // Arrange
        $host = new TwoTierThrottleHost();
        $ipKeyPrefix = $this->ipKeyPrefix();
        $host->attempt($this->accountKey(), 5, $ipKeyPrefix, 1);

        // Act
        $blocked = $host->attempt($this->accountKey(), 5, $ipKeyPrefix, 1);

        // Assert
        $this->assertTrue($blocked);
        $this->assertStringContainsString('this network', FormController::formAlerts());
    }

    protected function setUp(): void
    {
        FormController::$alerts = [];
    }

    protected function tearDown(): void
    {
        foreach ($this->accountKeys as $key) RateLimiter::clear($key);
        foreach ($this->ipKeyPrefixes as $prefix) RateLimiter::clear(RateLimiter::ipKey($prefix));
    }
}
