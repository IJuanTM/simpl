<?php

declare(strict_types=1);

namespace tests\Controllers;

use app\Controllers\RequestController;
use PHPUnit\Framework\TestCase;

class RequestControllerTest extends TestCase
{
    public function testPostReturnsNullWhenMissing(): void
    {
        $this->assertNull(RequestController::post('missing'));
    }

    public function testPostSanitizesTheValue(): void
    {
        $_POST['name'] = '<b>x</b>';

        $this->assertSame('&lt;b&gt;x&lt;/b&gt;', RequestController::post('name'));
    }

    public function testPostReturnsNullForANonStringValue(): void
    {
        $_POST['tags'] = ['a', 'b'];

        $this->assertNull(RequestController::post('tags'));
    }

    public function testRawPostReturnsTheValueUnsanitized(): void
    {
        $_POST['name'] = '<b>x</b>';

        $this->assertSame('<b>x</b>', RequestController::rawPost('name'));
    }

    public function testRawPostReturnsNullWhenMissing(): void
    {
        $this->assertNull(RequestController::rawPost('missing'));
    }

    public function testGetReturnsNullWhenMissing(): void
    {
        $this->assertNull(RequestController::get('missing'));
    }

    public function testGetSanitizesTheValue(): void
    {
        $_GET['q'] = '<script>';

        $this->assertSame('&lt;script&gt;', RequestController::get('q'));
    }

    public function testGetReturnsNullForANonStringValue(): void
    {
        $_GET['ids'] = ['1', '2'];

        $this->assertNull(RequestController::get('ids'));
    }

    protected function setUp(): void
    {
        $_POST = [];
        $_GET = [];
    }
}
