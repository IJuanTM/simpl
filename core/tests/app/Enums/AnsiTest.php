<?php

declare(strict_types=1);

namespace tests\Enums;

use app\Enums\Ansi;
use PHPUnit\Framework\TestCase;

class AnsiTest extends TestCase
{
    public function testWrapWithNoStylesReturnsTheMessageUnchanged(): void
    {
        $this->assertSame('hello', Ansi::wrap('hello'));
    }

    public function testWrapWithOneStyleWrapsWithThatStyleAndReset(): void
    {
        $this->assertSame(Ansi::GREEN->value . 'hello' . Ansi::RESET->value, Ansi::wrap('hello', Ansi::GREEN));
    }

    public function testWrapWithMultipleStylesConcatenatesAllStylesBeforeTheMessage(): void
    {
        $expected = Ansi::BOLD->value . Ansi::RED->value . 'hello' . Ansi::RESET->value;

        $this->assertSame($expected, Ansi::wrap('hello', Ansi::BOLD, Ansi::RED));
    }

    public function testSequenceWithNoStylesReturnsAnEmptyString(): void
    {
        $this->assertSame('', Ansi::sequence());
    }

    public function testSequenceConcatenatesStyleCodesInTheGivenOrder(): void
    {
        $this->assertSame(Ansi::CYAN->value . Ansi::DIM->value, Ansi::sequence(Ansi::CYAN, Ansi::DIM));
    }
}
