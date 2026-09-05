<?php

declare(strict_types=1);

namespace tests\Database\Migrations;

use app\Database\Migrations\Blueprint;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * The column/index/foreign builders only accumulate fragments in private properties.
 * build() is never called here, so none of this touches DB::raw() or a real connection.
 */
final class BlueprintTest extends TestCase
{
    public function testColumnWithANumericDefault(): void
    {
        // Arrange + Act
        $blueprint = (new Blueprint('users'))->intUnsigned('login_attempts', false, 0);

        // Assert
        $this->assertSame(['`login_attempts` INT UNSIGNED DEFAULT 0'], $this->columns($blueprint));
    }

    private function columns(Blueprint $blueprint): array
    {
        return (new ReflectionProperty(Blueprint::class, 'columns'))->getValue($blueprint);
    }

    public function testVarcharWithNoOptions(): void
    {
        // Arrange + Act
        $blueprint = (new Blueprint('users'))->varchar('email');

        // Assert
        $this->assertSame(['`email` VARCHAR(255)'], $this->columns($blueprint));
    }

    public function testVarcharWithLengthAndNotNull(): void
    {
        // Arrange + Act
        $blueprint = (new Blueprint('users'))->varchar('username', 100, true);

        // Assert
        $this->assertSame(['`username` VARCHAR(100) NOT NULL'], $this->columns($blueprint));
    }

    public function testColumnWithANullDefault(): void
    {
        // Arrange + Act
        $blueprint = (new Blueprint('users'))->varchar('middle_name', 255, false, null);

        // Assert
        $this->assertSame(['`middle_name` VARCHAR(255) DEFAULT NULL'], $this->columns($blueprint));
    }

    public function testColumnWithAStringDefaultIsQuoted(): void
    {
        // Arrange + Act
        $blueprint = (new Blueprint('users'))->varchar('status', 20, false, 'active');

        // Assert
        $this->assertSame(["`status` VARCHAR(20) DEFAULT 'active'"], $this->columns($blueprint));
    }

    public function testTextColumnNeverGetsADefaultClause(): void
    {
        // Arrange + Act
        $blueprint = (new Blueprint('users'))->text('bio', true);

        // Assert
        $this->assertSame(['`bio` TEXT NOT NULL'], $this->columns($blueprint));
    }

    public function testColumnWithACurrentTimestampDefault(): void
    {
        // Arrange + Act
        $blueprint = (new Blueprint('users'))->timestamp('created_at', true, 'CURRENT_TIMESTAMP');

        // Assert
        $this->assertSame(['`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'], $this->columns($blueprint));
    }

    public function testTimestampWithAnExplicitNullDefaultEmitsDefaultNull(): void
    {
        // Arrange + Act
        $blueprint = (new Blueprint('users'))->timestamp('password_changed_at', default: null);

        // Assert
        $this->assertSame(['`password_changed_at` TIMESTAMP DEFAULT NULL'], $this->columns($blueprint));
    }

    public function testEnumBuildsAQuotedValueList(): void
    {
        // Arrange + Act
        $blueprint = (new Blueprint('users'))->enum('status', ['active', 'deactivated', 'deleted'], true);

        // Assert
        $this->assertSame(["`status` ENUM('active', 'deactivated', 'deleted') NOT NULL"], $this->columns($blueprint));
    }

    public function testAutoIncrementAppendsToTheLastColumn(): void
    {
        // Arrange + Act
        $blueprint = (new Blueprint('users'))->bigintUnsigned('id')->autoIncrement();

        // Assert
        $this->assertSame(['`id` BIGINT UNSIGNED AUTO_INCREMENT'], $this->columns($blueprint));
    }

    public function testAutoIncrementWithAStartingValueIsStoredSeparately(): void
    {
        // Arrange
        $prop = new ReflectionProperty(Blueprint::class, 'startAt');

        // Act
        $blueprint = (new Blueprint('users'))->bigintUnsigned('id')->autoIncrement(100);

        // Assert
        $this->assertSame(100, $prop->getValue($blueprint));
    }

    public function testUniqueIndexesTheLastColumnByName(): void
    {
        // Arrange + Act
        $blueprint = (new Blueprint('users'))->varchar('email')->unique();

        // Assert
        $this->assertSame(['UNIQUE (`email`)'], $this->indexes($blueprint));
    }

    private function indexes(Blueprint $blueprint): array
    {
        return (new ReflectionProperty(Blueprint::class, 'indexes'))->getValue($blueprint);
    }

    public function testOnUpdateCurrentTimestampAppendsToTheLastColumn(): void
    {
        // Arrange + Act
        $blueprint = (new Blueprint('users'))->timestamp('updated_at')->onUpdateCurrentTimestamp();

        // Assert
        $this->assertSame(['`updated_at` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'], $this->columns($blueprint));
    }

    public function testPrimaryWithASingleColumn(): void
    {
        // Arrange + Act
        $blueprint = (new Blueprint('users'))->primary('id');

        // Assert
        $this->assertSame('`id`', $this->primaryKey($blueprint));
    }

    private function primaryKey(Blueprint $blueprint): ?string
    {
        return (new ReflectionProperty(Blueprint::class, 'primaryKey'))->getValue($blueprint);
    }

    public function testPrimaryWithACompositeKey(): void
    {
        // Arrange + Act
        $blueprint = (new Blueprint('user_roles'))->primary('user_id', 'role_id');

        // Assert
        $this->assertSame('`user_id`, `role_id`', $this->primaryKey($blueprint));
    }

    public function testForeignWithDefaults(): void
    {
        // Arrange + Act
        $blueprint = (new Blueprint('tokens'))->foreign('user_id', 'users');

        // Assert
        $this->assertSame(['FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE'], $this->foreigns($blueprint));
    }

    private function foreigns(Blueprint $blueprint): array
    {
        return (new ReflectionProperty(Blueprint::class, 'foreigns'))->getValue($blueprint);
    }

    public function testForeignWithExplicitColumnAndOnDelete(): void
    {
        // Arrange + Act
        $blueprint = (new Blueprint('user_roles'))->foreign('role_id', 'roles', 'id', 'RESTRICT');

        // Assert
        $this->assertSame(['FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT'], $this->foreigns($blueprint));
    }

    public function testIndexWithMultipleColumns(): void
    {
        // Arrange + Act
        $blueprint = (new Blueprint('login_attempts'))->index('idx_ip_created', ['ip_address', 'created_at']);

        // Assert
        $this->assertSame(['INDEX `idx_ip_created` (`ip_address`, `created_at`)'], $this->indexes($blueprint));
    }

    public function testColumnDefinitionsAccumulateInDeclarationOrder(): void
    {
        // Arrange + Act
        $blueprint = (new Blueprint('users'))
            ->bigintUnsigned('id', true)->autoIncrement()
            ->varchar('email', 150, true)->unique();

        // Assert
        $this->assertSame(['`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT', '`email` VARCHAR(150) NOT NULL'], $this->columns($blueprint));
        $this->assertSame(['UNIQUE (`email`)'], $this->indexes($blueprint));
    }
}
