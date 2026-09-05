<?php

declare(strict_types=1);

namespace tests\Models;

use app\Models\Url;
use PHPUnit\Framework\TestCase;

final class UrlTest extends TestCase
{
    public function testFileAppendsAVersionQueryParameterForAnExistingFile(): void
    {
        // Act
        $url = Url::file('index.php');

        // Assert
        $this->assertStringStartsWith('/index.php?v=', $url);
        $this->assertMatchesRegularExpression('/\?v=\d+$/', $url);
    }

    public function testFileFallsBackToTheBareUrlWhenTheFileIsMissing(): void
    {
        // Act
        $url = Url::file('does-not-exist-' . uniqid() . '.css');

        // Assert
        $this->assertStringNotContainsString('?v=', $url);
    }

    public function testToNormalizesAMissingLeadingSlash(): void
    {
        // Act + Assert
        $this->assertSame('/page/sub', Url::to('page/sub'));
    }

    public function testToAvoidsADoubleLeadingSlash(): void
    {
        // Act + Assert
        $this->assertSame('/page/sub', Url::to('/page/sub'));
    }

    public function testToWithNoArgumentReturnsTheRoot(): void
    {
        // Act + Assert
        $this->assertSame('/', Url::to());
    }

    public function testAbsoluteJoinsAppUrlAndTheSubPath(): void
    {
        // Act + Assert
        $this->assertSame(rtrim(APP_URL, '/') . '/page', Url::absolute('page'));
    }

    public function testAbsoluteStripsAnExtraSlashBetweenAppUrlAndTheSubPath(): void
    {
        // Act + Assert
        $this->assertSame(rtrim(APP_URL, '/') . '/page', Url::absolute('/page'));
    }
}
