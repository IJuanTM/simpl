<?php

declare(strict_types=1);

namespace tests\Unit\Enums;

use app\Enums\TwoFactorMethod;
use PHPUnit\Framework\TestCase;

/**
 * label() is a match expression with no default arm, so this guards that every case stays covered.
 * An uncovered case throws UnhandledMatchError, not a silent gap.
 */
final class TwoFactorMethodTest extends TestCase
{
    public function testEveryCaseHasANonEmptyLabel(): void
    {
        // Act + Assert
        foreach (TwoFactorMethod::cases() as $case) $this->assertNotSame('', $case->label(), $case->name . ' has no label');
    }

    public function testLabelMatchesTheCaseRatherThanReturningAConstantString(): void
    {
        // Act + Assert
        $this->assertSame('Email', TwoFactorMethod::EMAIL->label());
        $this->assertSame('Authenticator app', TwoFactorMethod::TOTP->label());
        $this->assertSame('Passkeys', TwoFactorMethod::PASSKEY->label());
    }
}
