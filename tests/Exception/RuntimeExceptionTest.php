<?php

namespace Tourze\TronAPI\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use Tourze\TronAPI\Exception\RuntimeException;

/**
 * @internal
 */
#[CoversClass(RuntimeException::class)]
class RuntimeExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionCanBeCreated(): void
    {
        $exception = new RuntimeException('Runtime error');
        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
    }

    public function testExceptionMessage(): void
    {
        $message = 'Runtime error message';
        $exception = new RuntimeException($message);
        $this->assertSame($message, $exception->getMessage());
    }
}
