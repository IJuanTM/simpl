<?php

declare(strict_types=1);

namespace tests\Enums;

use app\Enums\ErrorCode;
use PHPUnit\Framework\TestCase;

/**
 * message() is a match expression with no default arm, so this guards that every
 * case stays covered - an uncovered case throws UnhandledMatchError, not a silent gap.
 */
class ErrorCodeTest extends TestCase
{
    public function testEveryCaseHasANonEmptyMessage(): void
    {
        foreach (ErrorCode::cases() as $case) $this->assertNotSame('', $case->message(), $case->name . ' has no message');
    }
}
