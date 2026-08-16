<?php

declare(strict_types=1);

namespace tests\Models;

use app\Models\Alias;
use PHPUnit\Framework\TestCase;

class AliasTest extends TestCase
{
    public function testEvaluateUsesTheProvidedOverrideWhenPresent(): void
    {
        $alias = new Alias('page', [], ['id' => 1]);

        $this->assertSame(['id' => 42], $alias->evaluate(['id' => 42]));
    }

    public function testEvaluateFallsBackToTheDefaultValue(): void
    {
        $alias = new Alias('page', [], ['id' => 1]);

        $this->assertSame(['id' => 1], $alias->evaluate([]));
    }

    public function testEvaluateCallsCallableDefaultsWhenNoOverrideIsGiven(): void
    {
        $alias = new Alias('page', [], ['token' => static fn() => 'generated']);

        $this->assertSame(['token' => 'generated'], $alias->evaluate([]));
    }

    public function testEvaluatePrefersAnOverrideOverACallableDefault(): void
    {
        $alias = new Alias('page', [], ['token' => static fn() => 'generated']);

        $this->assertSame(['token' => 'explicit'], $alias->evaluate(['token' => 'explicit']));
    }
}
