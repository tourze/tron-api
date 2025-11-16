<?php

namespace Tourze\TronAPI\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use Tourze\TronAPI\Exception\UnsupportedOperationException;

/**
 * @internal
 */
#[CoversClass(UnsupportedOperationException::class)]
class UnsupportedOperationExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionCanBeCreated(): void
    {
        $exception = new UnsupportedOperationException('Unsupported operation');
        $this->assertInstanceOf(UnsupportedOperationException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
    }

    public function testExceptionMessage(): void
    {
        $message = 'Operation not supported';
        $exception = new UnsupportedOperationException($message);
        $this->assertSame($message, $exception->getMessage());
    }
}
