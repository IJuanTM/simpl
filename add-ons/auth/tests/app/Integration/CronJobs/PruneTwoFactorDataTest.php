<?php

declare(strict_types=1);

namespace tests\Integration\CronJobs;

use app\Cron\PruneTwoFactorData;
use app\Database\DB;
use app\Enums\TokenType;
use tests\Integration\IntegrationTestCase;

final class PruneTwoFactorDataTest extends IntegrationTestCase
{
    public function testRunPrunesExpiredAndUsedRowsButKeepsLiveOnes(): void
    {
        // Arrange
        $userId = $this->insertUser();
        $expiredDevice = $this->insertTrustedDevice($userId, '-1 hour');
        $liveDevice = $this->insertTrustedDevice($userId, '+1 hour');
        $expiredCode = $this->insertEmailCode($userId, '-1 hour');
        $liveCode = $this->insertEmailCode($userId, '+1 hour');
        $usedRecoveryCode = $this->insertRecoveryCode($userId, used: true);
        $unusedRecoveryCode = $this->insertRecoveryCode($userId, used: false);

        // Act
        PruneTwoFactorData::run();

        // Assert
        $this->assertNull(DB::single(SELECT: 'id', FROM: 'trusted_devices', WHERE: ['id' => $expiredDevice]));
        $this->assertNotNull(DB::single(SELECT: 'id', FROM: 'trusted_devices', WHERE: ['id' => $liveDevice]));
        $this->assertNull(DB::single(SELECT: 'id', FROM: 'tokens', WHERE: ['id' => $expiredCode]));
        $this->assertNotNull(DB::single(SELECT: 'id', FROM: 'tokens', WHERE: ['id' => $liveCode]));
        $this->assertNull(DB::single(SELECT: 'id', FROM: 'two_factor_recovery_codes', WHERE: ['id' => $usedRecoveryCode]));
        $this->assertNotNull(DB::single(SELECT: 'id', FROM: 'two_factor_recovery_codes', WHERE: ['id' => $unusedRecoveryCode]));
    }

    private function insertUser(): int
    {
        DB::insert(INTO: 'users', VALUES: [
            'email' => uniqid('user_', true) . '@example.test',
            'password' => 'hashed-password',
        ]);
        return (int)DB::lastInsertId();
    }

    private function insertTrustedDevice(int $userId, string $expiresOffset): int
    {
        DB::insert(INTO: 'trusted_devices', VALUES: [
            'user_id' => $userId,
            'selector' => bin2hex(random_bytes(8)),
            'validator_hash' => bin2hex(random_bytes(16)),
            'expires' => date('Y-m-d H:i:s', strtotime($expiresOffset)),
        ]);
        return (int)DB::lastInsertId();
    }

    private function insertEmailCode(int $userId, string $expiresOffset): int
    {
        DB::insert(INTO: 'tokens', VALUES: [
            'user_id' => $userId,
            'token' => bin2hex(random_bytes(16)),
            'type' => TokenType::TWO_FACTOR_EMAIL->value,
            'expires' => date('Y-m-d H:i:s', strtotime($expiresOffset)),
        ]);
        return (int)DB::lastInsertId();
    }

    private function insertRecoveryCode(int $userId, bool $used): int
    {
        DB::insert(INTO: 'two_factor_recovery_codes', VALUES: [
            'user_id' => $userId,
            'code_hash' => bin2hex(random_bytes(16)),
            'used_at' => $used ? date('Y-m-d H:i:s') : null,
        ]);
        return (int)DB::lastInsertId();
    }
}
