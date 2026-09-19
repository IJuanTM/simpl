<?php

declare(strict_types=1);

namespace tests\Integration\CronJobs;

use app\Cron\DeleteDeactivatedUsers;
use app\Database\DB;
use app\Enums\UserStatus;
use tests\Integration\IntegrationTestCase;

/**
 * INACTIVE_USER_CONFIG['deletion_after_days'] is 7 (auth.php), so the fixtures use an 8-day-old
 * inactive_since to guarantee it's past the cutoff and a 1-day-old one to guarantee it isn't.
 */
final class DeleteDeactivatedUsersTest extends IntegrationTestCase
{
    public function testRunDeletesADeactivatedUserPastTheRetentionWindow(): void
    {
        // Arrange
        $userId = $this->insertUser(UserStatus::DEACTIVATED, '-8 days');

        // Act
        DeleteDeactivatedUsers::run();

        // Assert
        $this->assertNull(DB::single(SELECT: 'id', FROM: 'users', WHERE: ['id' => $userId]));
    }

    private function insertUser(UserStatus $status, string $inactiveSinceOffset): int
    {
        DB::insert(INTO: 'users', VALUES: [
            'email' => uniqid('user_', true) . '@example.test',
            'password' => 'hashed-password',
            'status' => $status->value,
            'inactive_since' => date('Y-m-d H:i:s', strtotime($inactiveSinceOffset)),
        ]);
        return (int)DB::lastInsertId();
    }

    public function testRunLeavesARecentlyDeactivatedUserAlone(): void
    {
        // Arrange
        $userId = $this->insertUser(UserStatus::DEACTIVATED, '-1 day');

        // Act
        DeleteDeactivatedUsers::run();

        // Assert
        $this->assertNotNull(DB::single(SELECT: 'id', FROM: 'users', WHERE: ['id' => $userId]));
    }
}
