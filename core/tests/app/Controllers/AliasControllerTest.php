<?php

declare(strict_types=1);

namespace tests\Controllers;

use app\Controllers\AliasController;
use app\Models\Alias;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class AliasControllerTest extends TestCase
{
    public function testConstructorRegistersTheWelcomeAlias(): void
    {
        // Act
        new AliasController();

        // Assert
        $resolved = AliasController::resolve('welcome', []);
        $this->assertSame('home', $resolved['page']);
    }

    public function testRegisterAndResolveRoundTrip(): void
    {
        // Arrange
        $name = 'alias-' . uniqid();
        AliasController::register($name, new Alias('target-page', ['sub'], ['q' => 'default']));

        // Act
        $resolved = AliasController::resolve($name, []);

        // Assert
        $this->assertSame('target-page', $resolved['page']);
        $this->assertSame(['sub'], $resolved['subpages']);
        $this->assertSame(['q' => 'default'], $resolved['params']);
    }

    public function testResolveReturnsNullForAnUnknownAlias(): void
    {
        // Act + Assert
        $this->assertNull(AliasController::resolve('does-not-exist-' . uniqid(), []));
    }

    public function testResolveEvaluatesParamsAgainstTheProvidedOverrides(): void
    {
        // Arrange
        $name = 'alias-' . uniqid();
        AliasController::register($name, new Alias('target-page', [], ['q' => 'default']));

        // Act
        $resolved = AliasController::resolve($name, ['q' => 'override']);

        // Assert
        $this->assertSame(['q' => 'override'], $resolved['params']);
    }

    protected function setUp(): void
    {
        (new ReflectionProperty(AliasController::class, 'aliases'))->setValue(null, []);
    }
}
