<?php

namespace Rocont\CompassChannel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rocont\CompassChannel\Exceptions\CompassException;

class CompassExceptionTest extends TestCase
{
    public function test_compass_code_is_accessible(): void
    {
        $e = new CompassException('error', 42, ['foo' => 'bar']);

        $this->assertSame(42, $e->getCompassCode());
    }

    public function test_response_is_accessible(): void
    {
        $e = new CompassException('error', null, ['foo' => 'bar']);

        $this->assertSame(['foo' => 'bar'], $e->getResponse());
    }

    public function test_defaults_are_null_and_empty(): void
    {
        $e = new CompassException('error');

        $this->assertNull($e->getCompassCode());
        $this->assertSame([], $e->getResponse());
    }

    public function test_previous_throwable_is_preserved(): void
    {
        $prev = new \RuntimeException('previous');
        $e = new CompassException('error', null, [], 0, $prev);

        $this->assertSame($prev, $e->getPrevious());
    }
}
