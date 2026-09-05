<?php

declare(strict_types=1);

namespace tests\Models;

use app\Models\Alias;
use PHPUnit\Framework\TestCase;

final class AliasTest extends TestCase
{
    public function testEvaluateUsesTheProvidedOverrideWhenPresent(): void
    {
        // Arrange
        $alias = new Alias('page', [], ['id' => 1]);

        // Act + Assert
        $this->assertSame(['id' => 42], $alias->evaluate(['id' => 42]));
    }

    public function testEvaluateFallsBackToTheDefaultValue(): void
    {
        // Arrange
        $alias = new Alias('page', [], ['id' => 1]);

        // Act + Assert
        $this->assertSame(['id' => 1], $alias->evaluate([]));
    }

    public function testEvaluateCallsCallableDefaultsWhenNoOverrideIsGiven(): void
    {
        // Arrange
        $alias = new Alias('page', [], ['token' => static fn() => 'generated']);

        // Act + Assert
        $this->assertSame(['token' => 'generated'], $alias->evaluate([]));
    }

    public function testEvaluatePrefersAnOverrideOverACallableDefault(): void
    {
        // Arrange
        $alias = new Alias('page', [], ['token' => static fn() => 'generated']);

        // Act + Assert
        $this->assertSame(['token' => 'explicit'], $alias->evaluate(['token' => 'explicit']));
    }
}
