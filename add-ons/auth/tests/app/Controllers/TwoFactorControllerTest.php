<?php

declare(strict_types=1);

namespace tests\Controllers;

use app\Controllers\TwoFactorController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class TwoFactorControllerTest extends TestCase
{
    public function testAdminRoleIsRequiredToUse2fa(): void
    {
        // Arrange + Act + Assert
        // 'Admin' is in the shipped TWO_FACTOR_CONFIG['force_for_roles'].
        $this->assertTrue(TwoFactorController::isRequiredFor(['role' => 'Admin']));
    }

    public function testIsRequiredForIgnoresAnUnlistedOrMissingRole(): void
    {
        // Act + Assert
        $this->assertFalse(TwoFactorController::isRequiredFor(['role' => 'definitely-not-a-real-role']));
        $this->assertFalse(TwoFactorController::isRequiredFor([]));
    }

    public function testNumericCodeIsZeroPaddedToTheConfiguredLength(): void
    {
        // Arrange
        $numericCode = new ReflectionMethod(TwoFactorController::class, 'numericCode');
        $pattern = '/^\d{' . TWO_FACTOR_CONFIG['code_length'] . '}$/';

        // Act
        $codes = array_map(static fn() => $numericCode->invoke(null), range(1, 50));

        // Assert
        foreach ($codes as $code) $this->assertMatchesRegularExpression($pattern, $code);
    }

    public function testQrSvgRendersAStandaloneSvgElement(): void
    {
        // Arrange
        $qrSvg = new ReflectionMethod(TwoFactorController::class, 'qrSvg');

        // Act
        $svg = $qrSvg->invoke(null, 'otpauth://totp/Simpl:a@b.c?secret=JBSWY3DPEHPK3PXP&issuer=Simpl');

        // Assert
        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringEndsWith('</svg>', trim($svg));
    }
}
