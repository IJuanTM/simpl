<?php

declare(strict_types=1);

namespace tests\Unit\Controllers;

use app\Controllers\AppController;
use PHPUnit\Framework\TestCase;

final class AppControllerUtilitiesTest extends TestCase
{
    public function testSanitizeEscapesHtmlSpecialCharacters(): void
    {
        // Act + Assert
        $this->assertSame('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', AppController::sanitize('<script>alert("x")</script>'));
    }

    public function testSanitizeTrimsSurroundingWhitespace(): void
    {
        // Act + Assert
        $this->assertSame('value', AppController::sanitize("  value  \n"));
    }

    public function testSanitizeEscapesSingleQuotes(): void
    {
        // Act + Assert
        // ENT_HTML5 renders the apostrophe as the named entity, not the numeric one.
        $this->assertSame('&apos;', AppController::sanitize("'"));
    }

    public function testSvgReturnsFileContentsWhenPresent(): void
    {
        // Act + Assert
        $this->assertStringContainsString('<svg', AppController::svg('simpl'));
    }

    public function testSvgReturnsAPlaceholderWhenMissing(): void
    {
        // Act + Assert
        $this->assertStringContainsString('SVG "does-not-exist" not found', AppController::svg('does-not-exist'));
    }
}
