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
class BlueprintTest extends TestCase
{
    public function testVarcharWithNoOptions(): void
    {
        $blueprint = (new Blueprint('users'))->varchar('email');

        $this->assertSame(['`email` VARCHAR(255)'], $this->columns($blueprint));
    }

    private function columns(Blueprint $blueprint): array
    {
        $prop = new ReflectionProperty(Blueprint::class, 'columns');
        return $prop->getValue($blueprint);
    }

    public function testVarcharWithLengthAndNotNull(): void
    {
        $blueprint = (new Blueprint('users'))->varchar('username', 100, true);

        $this->assertSame(['`username` VARCHAR(100) NOT NULL'], $this->columns($blueprint));
    }

    public function testColumnWithANullDefault(): void
    {
        $blueprint = (new Blueprint('users'))->varchar('middle_name', 255, false, null);

        $this->assertSame(['`middle_name` VARCHAR(255) DEFAULT NULL'], $this->columns($blueprint));
    }

    public function testColumnWithACurrentTimestampDefault(): void
    {
        $blueprint = (new Blueprint('users'))->timestamp('created_at', true, 'CURRENT_TIMESTAMP');

        $this->assertSame(['`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'], $this->columns($blueprint));
    }

    public function testColumnWithANumericDefault(): void
    {
        $blueprint = (new Blueprint('users'))->intUnsigned('login_attempts', false, 0);

        $this->assertSame(['`login_attempts` INT UNSIGNED DEFAULT 0'], $this->columns($blueprint));
    }

    public function testColumnWithAStringDefaultIsQuoted(): void
    {
        $blueprint = (new Blueprint('users'))->varchar('status', 20, false, 'active');

        $this->assertSame(["`status` VARCHAR(20) DEFAULT 'active'"], $this->columns($blueprint));
    }

    public function testTextColumnNeverGetsADefaultClause(): void
    {
        $blueprint = (new Blueprint('users'))->text('bio', true);

        $this->assertSame(['`bio` TEXT NOT NULL'], $this->columns($blueprint));
    }

    public function testEnumBuildsAQuotedValueList(): void
    {
        $blueprint = (new Blueprint('users'))->enum('status', ['active', 'deactivated', 'deleted'], true);

        $this->assertSame(["`status` ENUM('active', 'deactivated', 'deleted') NOT NULL"], $this->columns($blueprint));
    }

    public function testAutoIncrementAppendsToTheLastColumn(): void
    {
        $blueprint = (new Blueprint('users'))->bigintUnsigned('id')->autoIncrement();

        $this->assertSame(['`id` BIGINT UNSIGNED AUTO_INCREMENT'], $this->columns($blueprint));
    }

    public function testAutoIncrementWithAStartingValueIsStoredSeparately(): void
    {
        $prop = new ReflectionProperty(Blueprint::class, 'startAt');
        $blueprint = (new Blueprint('users'))->bigintUnsigned('id')->autoIncrement(100);

        $this->assertSame(100, $prop->getValue($blueprint));
    }

    public function testUniqueIndexesTheLastColumnByName(): void
    {
        $blueprint = (new Blueprint('users'))->varchar('email')->unique();

        $this->assertSame(['UNIQUE (`email`)'], $this->indexes($blueprint));
    }

    private function indexes(Blueprint $blueprint): array
    {
        $prop = new ReflectionProperty(Blueprint::class, 'indexes');
        return $prop->getValue($blueprint);
    }

    public function testOnUpdateCurrentTimestampAppendsToTheLastColumn(): void
    {
        $blueprint = (new Blueprint('users'))->timestamp('updated_at')->onUpdateCurrentTimestamp();

        $this->assertSame(['`updated_at` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'], $this->columns($blueprint));
    }

    public function testPrimaryWithASingleColumn(): void
    {
        $blueprint = (new Blueprint('users'))->primary('id');

        $this->assertSame('`id`', $this->primaryKey($blueprint));
    }

    private function primaryKey(Blueprint $blueprint): ?string
    {
        $prop = new ReflectionProperty(Blueprint::class, 'primaryKey');
        return $prop->getValue($blueprint);
    }

    public function testPrimaryWithACompositeKey(): void
    {
        $blueprint = (new Blueprint('user_roles'))->primary('user_id', 'role_id');

        $this->assertSame('`user_id`, `role_id`', $this->primaryKey($blueprint));
    }

    public function testForeignWithDefaults(): void
    {
        $blueprint = (new Blueprint('tokens'))->foreign('user_id', 'users');

        $this->assertSame(['FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE'], $this->foreigns($blueprint));
    }

    private function foreigns(Blueprint $blueprint): array
    {
        $prop = new ReflectionProperty(Blueprint::class, 'foreigns');
        return $prop->getValue($blueprint);
    }

    public function testForeignWithExplicitColumnAndOnDelete(): void
    {
        $blueprint = (new Blueprint('user_roles'))->foreign('role_id', 'roles', 'id', 'RESTRICT');

        $this->assertSame(['FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT'], $this->foreigns($blueprint));
    }

    public function testIndexWithMultipleColumns(): void
    {
        $blueprint = (new Blueprint('login_attempts'))->index('idx_ip_created', ['ip_address', 'created_at']);

        $this->assertSame(['INDEX `idx_ip_created` (`ip_address`, `created_at`)'], $this->indexes($blueprint));
    }

    public function testColumnDefinitionsAccumulateInDeclarationOrder(): void
    {
        $blueprint = (new Blueprint('users'))
            ->bigintUnsigned('id', true)->autoIncrement()
            ->varchar('email', 150, true)->unique();

        $this->assertSame(['`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT', '`email` VARCHAR(150) NOT NULL'], $this->columns($blueprint));
        $this->assertSame(['UNIQUE (`email`)'], $this->indexes($blueprint));
    }
}
