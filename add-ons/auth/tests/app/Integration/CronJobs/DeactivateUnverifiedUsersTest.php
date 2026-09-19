<?php

declare(strict_types=1);

namespace tests\Integration\CronJobs;

use app\Cron\DeactivateUnverifiedUsers;
use app\Database\DB;
use app\Enums\TokenType;
use app\Enums\UserStatus;
use tests\Integration\IntegrationTestCase;

/**
 * INACTIVE_USER_CONFIG['unverified_deactivation_after_days'] is 1 day (auth.php), so the fixtures use
 * a 2-day-old token to guarantee it's past the cutoff and a 1-hour-old one to guarantee it isn't.
 */
final class DeactivateUnverifiedUsersTest extends IntegrationTestCase
{
    public function testRunDeactivatesAUserWhoseVerificationTokenExpired(): void
    {
        // Arrange
        $userId = $this->insertUser(UserStatus::ACTIVE);
        $this->insertVerificationToken($userId, '-2 days');

        // Act
        DeactivateUnverifiedUsers::run();

        // Assert
        $user = DB::single(SELECT: ['status', 'inactive_since'], FROM: 'users', WHERE: ['id' => $userId]);
        $this->assertSame(UserStatus::DEACTIVATED->value, $user['status']);
        $this->assertNotNull($user['inactive_since']);
    }

    private function insertUser(UserStatus $status): int
    {
        DB::insert(INTO: 'users', VALUES: [
            'email' => uniqid('user_', true) . '@example.test',
            'password' => 'hashed-password',
            'status' => $status->value,
        ]);
        return (int)DB::lastInsertId();
    }

    private function insertVerificationToken(int $userId, string $createdOffset): void
    {
        DB::insert(INTO: 'tokens', VALUES: [
            'user_id' => $userId,
            'token' => bin2hex(random_bytes(16)),
            'type' => TokenType::VERIFICATION->value,
            'created' => date('Y-m-d H:i:s', strtotime($createdOffset)),
        ]);
    }

    public function testRunLeavesAUserWithAFreshVerificationTokenAlone(): void
    {
        // Arrange
        $userId = $this->insertUser(UserStatus::ACTIVE);
        $this->insertVerificationToken($userId, '-1 hour');

        // Act
        DeactivateUnverifiedUsers::run();

        // Assert
        $user = DB::single(SELECT: 'status', FROM: 'users', WHERE: ['id' => $userId]);
        $this->assertSame(UserStatus::ACTIVE->value, $user['status']);
    }
}
