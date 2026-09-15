<?php

declare(strict_types=1);

namespace tests\Unit\Controllers;

use app\Controllers\WebauthnController;
use PHPUnit\Framework\TestCase;

/**
 * Only base64Url() and credentialIdFromResponse()'s failure branch are covered here; the rest of this
 * class runs real WebAuthn ceremonies and touches the session, which need browser-issued credentials.
 */
final class WebauthnControllerTest extends TestCase
{
    public function testBase64UrlProducesUrlSafeUnpaddedOutput(): void
    {
        // Act
        $encoded = WebauthnController::base64Url("\xFB\xFF\xFF");

        // Assert
        $this->assertSame('-___', $encoded);
        $this->assertStringNotContainsString('+', $encoded);
        $this->assertStringNotContainsString('/', $encoded);
        $this->assertStringNotContainsString('=', $encoded);
    }

    public function testCredentialIdFromResponseReturnsNullForMalformedJson(): void
    {
        // Act + Assert
        $this->assertNull(WebauthnController::credentialIdFromResponse('not valid json'));
    }
}
