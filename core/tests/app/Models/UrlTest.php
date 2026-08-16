<?php

declare(strict_types=1);

namespace tests\Models;

use app\Models\Url;
use PHPUnit\Framework\TestCase;

class UrlTest extends TestCase
{
    public function testToNormalizesAMissingLeadingSlash(): void
    {
        $this->assertSame('/page/sub', Url::to('page/sub'));
    }

    public function testToAvoidsADoubleLeadingSlash(): void
    {
        $this->assertSame('/page/sub', Url::to('/page/sub'));
    }

    public function testToWithNoArgumentReturnsTheRoot(): void
    {
        $this->assertSame('/', Url::to());
    }

    public function testAbsoluteJoinsAppUrlAndTheSubPath(): void
    {
        $this->assertSame(rtrim(APP_URL, '/') . '/page', Url::absolute('page'));
    }

    public function testAbsoluteStripsAnExtraSlashBetweenAppUrlAndTheSubPath(): void
    {
        $this->assertSame(rtrim(APP_URL, '/') . '/page', Url::absolute('/page'));
    }

    public function testFileAppendsAVersionQueryParameterForAnExistingFile(): void
    {
        $url = Url::file('index.php');

        $this->assertStringStartsWith('/index.php?v=', $url);
        $this->assertMatchesRegularExpression('/\?v=\d+$/', $url);
    }

    public function testFileFallsBackToTheBareUrlWhenTheFileIsMissing(): void
    {
        $url = Url::file('does-not-exist-' . uniqid() . '.css');

        $this->assertStringNotContainsString('?v=', $url);
    }
}
