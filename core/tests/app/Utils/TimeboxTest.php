<?php

declare(strict_types=1);

namespace tests\Utils;

use app\Utils\Timebox;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TimeboxTest extends TestCase
{
    public function testPadsExecutionTimeUpToFloor(): void
    {
        // Arrange
        $start = hrtime(true);

        // Act
        $result = (new Timebox())->call(static fn() => 'value', 20_000);
        $elapsedMs = (hrtime(true) - $start) / 1_000_000;

        // Assert
        $this->assertSame('value', $result);
        $this->assertGreaterThanOrEqual(20, $elapsedMs);
    }

    public function testDoesNotShortenACallbackThatAlreadyExceedsTheFloor(): void
    {
        // Arrange
        $start = hrtime(true);

        // Act
        $result = (new Timebox())->call(static function () {
            usleep(15_000);
            return 'slow';
        }, 5_000);
        $elapsedMs = (hrtime(true) - $start) / 1_000_000;

        // Assert
        $this->assertSame('slow', $result);
        $this->assertGreaterThanOrEqual(15, $elapsedMs);
    }

    public function testExceptionsPropagateAfterStillPaddingTheFloor(): void
    {
        // Arrange
        $start = hrtime(true);

        // Act + Assert
        try {
            (new Timebox())->call(static function () {
                throw new RuntimeException('boom');
            }, 20_000);
            $this->fail('Expected exception was not thrown');
        } catch (RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        // Assert
        $elapsedMs = (hrtime(true) - $start) / 1_000_000;
        $this->assertGreaterThanOrEqual(20, $elapsedMs);
    }

    public function testReturnEarlySkipsTheFloor(): void
    {
        // Arrange
        $start = hrtime(true);

        // Act
        (new Timebox())->call(static function (Timebox $box) {
            $box->returnEarly();
            return 'value';
        }, 200_000);
        $elapsedMs = (hrtime(true) - $start) / 1_000_000;

        // Assert
        $this->assertLessThan(100, $elapsedMs);
    }
}
