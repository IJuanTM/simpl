<?php

declare(strict_types=1);

namespace tests\Unit\Utils;

use app\Utils\UserAgentParser;
use PHPUnit\Framework\TestCase;

final class UserAgentParserTest extends TestCase
{
    public function testNullUserAgentDescribesAsUnknown(): void
    {
        // Act
        $result = UserAgentParser::describe(null);

        // Assert
        $this->assertSame(['type' => 'Unknown', 'os' => 'Unknown', 'browser' => null], $result);
    }

    public function testEmptyUserAgentDescribesAsUnknown(): void
    {
        // Act
        $result = UserAgentParser::describe('');

        // Assert
        $this->assertSame(['type' => 'Unknown', 'os' => 'Unknown', 'browser' => null], $result);
    }

    public function testDesktopWindowsChrome(): void
    {
        // Act
        $result = UserAgentParser::describe('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36');

        // Assert
        $this->assertSame(['type' => 'Desktop', 'os' => 'Windows', 'browser' => 'Chrome'], $result);
    }

    public function testMobileAndroidChrome(): void
    {
        // Act
        $result = UserAgentParser::describe('Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 Mobi Chrome/120.0.0.0 Mobile Safari/537.36');

        // Assert
        $this->assertSame(['type' => 'Mobile', 'os' => 'Android', 'browser' => 'Chrome'], $result);
    }

    public function testTabletIpadSafari(): void
    {
        // Act
        $result = UserAgentParser::describe('Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Version/17.0 Safari/604.1');

        // Assert
        $this->assertSame(['type' => 'Tablet', 'os' => 'iOS', 'browser' => 'Safari'], $result);
    }

    public function testMacOsFirefox(): void
    {
        // Act
        $result = UserAgentParser::describe('Mozilla/5.0 (Macintosh; Intel Mac OS X 14.0) Gecko/20100101 Firefox/120.0');

        // Assert
        $this->assertSame(['type' => 'Desktop', 'os' => 'macOS', 'browser' => 'Firefox'], $result);
    }

    public function testLinuxEdge(): void
    {
        // Act
        $result = UserAgentParser::describe('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0');

        // Assert
        $this->assertSame(['type' => 'Desktop', 'os' => 'Linux', 'browser' => 'Edge'], $result);
    }

    public function testOpera(): void
    {
        // Act
        $result = UserAgentParser::describe('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36 OPR/106.0.0.0');

        // Assert
        $this->assertSame('Opera', $result['browser']);
    }

    public function testIosSafariViaCriOsIsReportedAsChrome(): void
    {
        // Act
        $result = UserAgentParser::describe('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 CriOS/120.0.0.0 Mobile Safari/604.1');

        // Assert
        $this->assertSame(['type' => 'Mobile', 'os' => 'iOS', 'browser' => 'Chrome'], $result);
    }

    public function testUnrecognizedBrowserIsNull(): void
    {
        // Act
        $result = UserAgentParser::describe('SomeCustomBot/1.0');

        // Assert
        $this->assertNull($result['browser']);
    }
}
