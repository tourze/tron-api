<?php

namespace Tourze\TronAPI\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use Tourze\TronAPI\Exception\NotFoundException;

/**
 * @internal
 */
#[CoversClass(NotFoundException::class)]
class NotFoundExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionCanBeCreated(): void
    {
        $exception = new NotFoundException('Not found');
        $this->assertInstanceOf(NotFoundException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
    }

    public function testExceptionMessage(): void
    {
        $message = 'Resource not found';
        $exception = new NotFoundException($message);
        $this->assertSame($message, $exception->getMessage());
    }
}
