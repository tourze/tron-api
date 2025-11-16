<?php

namespace Tourze\TronAPI\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use Tourze\TronAPI\Exception\InvalidArgumentException;

/**
 * @internal
 */
#[CoversClass(InvalidArgumentException::class)]
class InvalidArgumentExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionCanBeCreated(): void
    {
        $exception = new InvalidArgumentException('Invalid argument');
        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
    }

    public function testExceptionMessage(): void
    {
        $message = 'Invalid argument message';
        $exception = new InvalidArgumentException($message);
        $this->assertSame($message, $exception->getMessage());
    }
}
