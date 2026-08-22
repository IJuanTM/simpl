<?php

declare(strict_types=1);

namespace tests\Controllers;

use app\Controllers\AliasController;
use app\Models\Alias;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class AliasControllerTest extends TestCase
{
    public function testResolveReturnsNullForAnUnknownAlias(): void
    {
        $this->assertNull(AliasController::resolve('does-not-exist-' . uniqid(), []));
    }

    public function testRegisterAndResolveRoundTrip(): void
    {
        $name = 'alias-' . uniqid();
        AliasController::register($name, new Alias('target-page', ['sub'], ['q' => 'default']));

        $resolved = AliasController::resolve($name, []);

        $this->assertSame('target-page', $resolved['page']);
        $this->assertSame(['sub'], $resolved['subpages']);
        $this->assertSame(['q' => 'default'], $resolved['params']);
    }

    public function testResolveEvaluatesParamsAgainstTheProvidedOverrides(): void
    {
        $name = 'alias-' . uniqid();
        AliasController::register($name, new Alias('target-page', [], ['q' => 'default']));

        $resolved = AliasController::resolve($name, ['q' => 'override']);

        $this->assertSame(['q' => 'override'], $resolved['params']);
    }

    public function testConstructorRegistersTheWelcomeAlias(): void
    {
        new AliasController();

        $resolved = AliasController::resolve('welcome', []);

        $this->assertSame('home', $resolved['page']);
    }

    protected function setUp(): void
    {
        (new ReflectionProperty(AliasController::class, 'aliases'))->setValue(null, []);
    }
}
