<?php

namespace Tourze\TronAPI\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use Tourze\TronAPI\Exception\TRC20Exception;

/**
 * @internal
 */
#[CoversClass(TRC20Exception::class)]
class TRC20ExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionCanBeCreated(): void
    {
        $exception = new TRC20Exception('TRC20 error');
        $this->assertInstanceOf(TRC20Exception::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
    }

    public function testExceptionMessage(): void
    {
        $message = 'TRC20 error message';
        $exception = new TRC20Exception($message);
        $this->assertSame($message, $exception->getMessage());
    }
}
