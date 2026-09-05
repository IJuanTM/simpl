<?php

declare(strict_types=1);

namespace tests\Enums;

use app\Enums\ErrorCode;
use PHPUnit\Framework\TestCase;

/**
 * message() is a match expression with no default arm, so this guards that every case stays covered.
 * An uncovered case throws UnhandledMatchError, not a silent gap.
 */
final class ErrorCodeTest extends TestCase
{
    public function testEveryCaseHasANonEmptyMessage(): void
    {
        // Act + Assert
        foreach (ErrorCode::cases() as $case) $this->assertNotSame('', $case->message(), $case->name . ' has no message');
    }

    public function testMessageMatchesTheCaseRatherThanReturningAConstantString(): void
    {
        // Act + Assert
        $this->assertStringContainsStringIgnoringCase('does not exist', ErrorCode::NOT_FOUND->message());
        $this->assertStringContainsStringIgnoringCase('no access', ErrorCode::FORBIDDEN->message());
        $this->assertStringContainsStringIgnoringCase('too many requests', ErrorCode::TOO_MANY_REQUESTS->message());
    }
}
