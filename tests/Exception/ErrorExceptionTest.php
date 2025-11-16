<?php

namespace Tourze\TronAPI\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use Tourze\TronAPI\Exception\ErrorException;

/**
 * @internal
 */
#[CoversClass(ErrorException::class)]
class ErrorExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionCanBeCreated(): void
    {
        $exception = new ErrorException('Test error');
        $this->assertInstanceOf(ErrorException::class, $exception);
        $this->assertInstanceOf(\ErrorException::class, $exception);
    }

    public function testExceptionMessage(): void
    {
        $message = 'Test error message';
        $exception = new ErrorException($message);
        $this->assertSame($message, $exception->getMessage());
    }
}
