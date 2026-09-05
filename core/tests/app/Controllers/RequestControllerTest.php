<?php

declare(strict_types=1);

namespace tests\Controllers;

use app\Controllers\RequestController;
use PHPUnit\Framework\TestCase;

final class RequestControllerTest extends TestCase
{
    public function testPostReturnsNullWhenMissing(): void
    {
        // Act + Assert
        $this->assertNull(RequestController::post('missing'));
    }

    public function testPostSanitizesTheValue(): void
    {
        // Arrange
        $_POST['name'] = '<b>x</b>';

        // Act + Assert
        $this->assertSame('&lt;b&gt;x&lt;/b&gt;', RequestController::post('name'));
    }

    public function testPostReturnsNullForANonStringValue(): void
    {
        // Arrange
        $_POST['tags'] = ['a', 'b'];

        // Act + Assert
        $this->assertNull(RequestController::post('tags'));
    }

    public function testRawPostReturnsTheValueUnsanitized(): void
    {
        // Arrange
        $_POST['name'] = '<b>x</b>';

        // Act + Assert
        $this->assertSame('<b>x</b>', RequestController::rawPost('name'));
    }

    public function testRawPostReturnsNullWhenMissing(): void
    {
        // Act + Assert
        $this->assertNull(RequestController::rawPost('missing'));
    }

    public function testGetReturnsNullWhenMissing(): void
    {
        // Act + Assert
        $this->assertNull(RequestController::get('missing'));
    }

    public function testGetSanitizesTheValue(): void
    {
        // Arrange
        $_GET['q'] = '<script>';

        // Act + Assert
        $this->assertSame('&lt;script&gt;', RequestController::get('q'));
    }

    public function testGetReturnsNullForANonStringValue(): void
    {
        // Arrange
        $_GET['ids'] = ['1', '2'];

        // Act + Assert
        $this->assertNull(RequestController::get('ids'));
    }

    protected function setUp(): void
    {
        $_POST = [];
        $_GET = [];
    }
}
