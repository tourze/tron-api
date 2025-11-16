<?php

namespace Tourze\TronAPI\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use Tourze\TronAPI\Exception\ValidationException;

/**
 * @internal
 */
#[CoversClass(ValidationException::class)]
class ValidationExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionCanBeCreated(): void
    {
        $exception = new ValidationException('Validation error');
        $this->assertInstanceOf(ValidationException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
    }

    public function testExceptionMessage(): void
    {
        $message = 'Validation failed';
        $exception = new ValidationException($message);
        $this->assertSame($message, $exception->getMessage());
    }
}
