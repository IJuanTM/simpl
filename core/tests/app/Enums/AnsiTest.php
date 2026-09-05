<?php

declare(strict_types=1);

namespace tests\Enums;

use app\Enums\Ansi;
use PHPUnit\Framework\TestCase;

final class AnsiTest extends TestCase
{
    public function testWrapWithNoStylesReturnsTheMessageUnchanged(): void
    {
        // Act + Assert
        $this->assertSame('hello', Ansi::wrap('hello'));
    }

    public function testWrapWithOneStyleWrapsWithThatStyleAndReset(): void
    {
        // Act + Assert
        $this->assertSame(Ansi::GREEN->value . 'hello' . Ansi::RESET->value, Ansi::wrap('hello', Ansi::GREEN));
    }

    public function testWrapWithMultipleStylesConcatenatesAllStylesBeforeTheMessage(): void
    {
        // Arrange
        $expected = Ansi::BOLD->value . Ansi::RED->value . 'hello' . Ansi::RESET->value;

        // Act + Assert
        $this->assertSame($expected, Ansi::wrap('hello', Ansi::BOLD, Ansi::RED));
    }

    public function testSequenceWithNoStylesReturnsAnEmptyString(): void
    {
        // Act + Assert
        $this->assertSame('', Ansi::sequence());
    }

    public function testSequenceConcatenatesStyleCodesInTheGivenOrder(): void
    {
        // Act + Assert
        $this->assertSame(Ansi::CYAN->value . Ansi::DIM->value, Ansi::sequence(Ansi::CYAN, Ansi::DIM));
    }
}
