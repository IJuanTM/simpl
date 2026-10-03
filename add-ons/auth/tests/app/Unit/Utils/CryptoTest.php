<?php

declare(strict_types=1);

namespace tests\Unit\Utils;

use app\Utils\Crypto;
use PHPUnit\Framework\TestCase;

final class CryptoTest extends TestCase
{
    public function testEncryptDecryptRoundTrip(): void
    {
        // Arrange
        $plaintext = 'JBSWY3DPEHPK3PXP';

        // Act
        $token = Crypto::encrypt($plaintext);

        // Assert
        $this->assertNotSame($plaintext, $token);
        $this->assertSame($plaintext, Crypto::decrypt($token));
    }

    public function testEachEncryptionUsesAFreshNonce(): void
    {
        // Act
        $a = Crypto::encrypt('same input');
        $b = Crypto::encrypt('same input');

        // Assert
        $this->assertNotSame($a, $b);
        $this->assertSame('same input', Crypto::decrypt($a));
        $this->assertSame('same input', Crypto::decrypt($b));
    }

    public function testDecryptRejectsATamperedToken(): void
    {
        // Arrange
        $raw = base64_decode(Crypto::encrypt('secret'), true);
        $raw[strlen($raw) - 1] = $raw[strlen($raw) - 1] ^ "\x01";

        // Act + Assert
        $this->assertNull(Crypto::decrypt(base64_encode($raw)));
    }

    public function testDecryptRejectsMalformedInput(): void
    {
        // Act + Assert
        $this->assertNull(Crypto::decrypt('not valid base64 @@@'));
        $this->assertNull(Crypto::decrypt(''));
        $this->assertNull(Crypto::decrypt(base64_encode('too short')));
    }
}
