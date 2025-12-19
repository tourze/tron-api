<?php

namespace Tourze\TronAPI\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\TronAPI\Exception\InvalidArgumentException;
use Tourze\TronAPI\Support\Crypto;

/**
 * @internal
 */
#[CoversClass(Crypto::class)]
class SupportCryptoTest extends TestCase
{
    public function testBin2bcAndBc2bin(): void
    {
        $binary = "\x01\x02\x03\x04";
        $bc = Crypto::bin2bc($binary);
        $this->assertIsString($bc);

        $result = Crypto::bc2bin($bc);
        $this->assertSame($binary, $result);
    }

    public function testDec2baseThrowsExceptionForInvalidBaseTooSmall(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Base: 1');
        // 使用反射绕过类型系统以测试运行时验证
        $method = new \ReflectionMethod(Crypto::class, 'dec2base');
        $method->invoke(null, '100', 1);
    }

    public function testDec2baseThrowsExceptionForInvalidBaseTooLarge(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Base: 257');
        // 使用反射绕过类型系统以测试运行时验证
        $method = new \ReflectionMethod(Crypto::class, 'dec2base');
        $method->invoke(null, '100', 257);
    }

    public function testBase2decThrowsExceptionForInvalidBaseTooSmall(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Base: 1');
        // 使用反射绕过类型系统以测试运行时验证
        $method = new \ReflectionMethod(Crypto::class, 'base2dec');
        $method->invoke(null, '100', 1);
    }

    public function testBase2decThrowsExceptionForInvalidBaseTooLarge(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Base: 257');
        // 使用反射绕过类型系统以测试运行时验证
        $method = new \ReflectionMethod(Crypto::class, 'base2dec');
        $method->invoke(null, '100', 257);
    }
}
