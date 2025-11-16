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
        // @phpstan-ignore argument.type
        Crypto::dec2base('100', 1);
    }

    public function testDec2baseThrowsExceptionForInvalidBaseTooLarge(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Base: 257');
        // @phpstan-ignore argument.type
        Crypto::dec2base('100', 257);
    }

    public function testBase2decThrowsExceptionForInvalidBaseTooSmall(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Base: 1');
        // @phpstan-ignore argument.type
        Crypto::base2dec('100', 1);
    }

    public function testBase2decThrowsExceptionForInvalidBaseTooLarge(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Base: 257');
        // @phpstan-ignore argument.type
        Crypto::base2dec('100', 257);
    }
}
