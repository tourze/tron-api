<?php

namespace Tourze\TronAPI\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use Tourze\TronAPI\Exception\RuntimeException;
use Tourze\TronAPI\Exception\TronException;

/**
 * @internal
 */
#[CoversClass(TronException::class)]
class TronExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionCanBeCreated(): void
    {
        // Test a concrete implementation of TronException
        $exception = new RuntimeException('Tron error');
        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }

    public function testExceptionMessage(): void
    {
        $message = 'Tron error message';
        $exception = new RuntimeException($message);
        $this->assertSame($message, $exception->getMessage());
    }
}
