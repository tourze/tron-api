<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Tests\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\TronAPI\Exception\InvalidArgumentException;
use Tourze\TronAPI\ValueObject\TokenBalance;

/**
 * @internal
 */
#[CoversClass(TokenBalance::class)]
class TokenBalanceTest extends TestCase
{
    public function testConstructor(): void
    {
        $balance = new TokenBalance('1000000', 6);
        $this->assertSame('1000000', $balance->rawBalance);
        $this->assertSame(6, $balance->decimals);
    }

    public function testFromRaw(): void
    {
        $balance = TokenBalance::fromRaw('1000000', 6);
        $this->assertSame('1000000', $balance->getRaw());
        $this->assertSame('1.000000', $balance->getScaled());
    }

    public function testFromScaled(): void
    {
        $balance = TokenBalance::fromScaled('1.5', 6);
        $this->assertSame('1500000', $balance->getRaw());
        // fromScaled 会缓存传入的 scaled 值
        $this->assertSame('1.5', $balance->getScaled());
    }

    public function testFromScaledWithZeroDecimals(): void
    {
        $balance = TokenBalance::fromScaled('100', 0);
        $this->assertSame('100', $balance->getRaw());
        $this->assertSame('100', $balance->getScaled());
    }

    public function testFromScaledWithHighDecimals(): void
    {
        $balance = TokenBalance::fromScaled('1.123456789012345678', 18);
        $this->assertSame('1123456789012345678', $balance->getRaw());
        $this->assertSame('1.123456789012345678', $balance->getScaled());
    }

    public function testGetRaw(): void
    {
        $balance = new TokenBalance('2500000', 6);
        $this->assertSame('2500000', $balance->getRaw());
    }

    public function testGetScaled(): void
    {
        $balance = new TokenBalance('2500000', 6);
        $this->assertSame('2.500000', $balance->getScaled());
    }

    public function testGetScaledCaching(): void
    {
        $balance = new TokenBalance('1000000', 6);
        // 第一次调用会计算
        $scaled1 = $balance->getScaled();
        // 第二次调用应该使用缓存
        $scaled2 = $balance->getScaled();
        $this->assertSame($scaled1, $scaled2);
        $this->assertSame('1.000000', $scaled2);
    }

    public function testGetScaledWithPreCachedValue(): void
    {
        $balance = new TokenBalance('1000000', 6, '1.000000');
        // 应该直接返回缓存值
        $this->assertSame('1.000000', $balance->getScaled());
    }

    public function testToFloat(): void
    {
        $balance = new TokenBalance('2500000', 6);
        $this->assertEqualsWithDelta(2.5, $balance->toFloat(), 0.000001);
    }

    public function testIsZero(): void
    {
        $zero = TokenBalance::fromRaw('0', 6);
        $this->assertTrue($zero->isZero());

        $nonZero = TokenBalance::fromRaw('1', 6);
        $this->assertFalse($nonZero->isZero());
    }

    public function testIsPositive(): void
    {
        $positive = TokenBalance::fromRaw('100', 6);
        $this->assertTrue($positive->isPositive());

        $zero = TokenBalance::fromRaw('0', 6);
        $this->assertFalse($zero->isPositive());
    }

    public function testCompareTo(): void
    {
        $balance1 = TokenBalance::fromRaw('1000000', 6);
        $balance2 = TokenBalance::fromRaw('2000000', 6);
        $balance3 = TokenBalance::fromRaw('1000000', 6);

        $this->assertSame(-1, $balance1->compareTo($balance2));
        $this->assertSame(1, $balance2->compareTo($balance1));
        $this->assertSame(0, $balance1->compareTo($balance3));
    }

    public function testCompareToWithDifferentDecimalsThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot compare balances with different decimals');

        $balance1 = TokenBalance::fromRaw('1000000', 6);
        $balance2 = TokenBalance::fromRaw('1000000', 18);
        $balance1->compareTo($balance2);
    }

    public function testFormat(): void
    {
        $balance = TokenBalance::fromRaw('1500000', 6);
        $this->assertSame('1.500000', $balance->format());
        $this->assertSame('1.500000 USDT', $balance->format('USDT'));
    }

    public function testFormatWithEmptySymbol(): void
    {
        $balance = TokenBalance::fromRaw('2000000', 6);
        $this->assertSame('2.000000', $balance->format(''));
    }

    public function testToArray(): void
    {
        $balance = TokenBalance::fromRaw('1500000', 6);
        $array = $balance->toArray();

        $this->assertSame('1500000', $array['raw']);
        $this->assertSame('1.500000', $array['scaled']);
        $this->assertSame(6, $array['decimals']);
    }

    public function testToString(): void
    {
        $balance = TokenBalance::fromRaw('1500000', 6);
        $this->assertSame('1.500000', (string) $balance);
    }

    public function testInvalidRawBalanceThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Raw balance must be a numeric string');

        new TokenBalance('invalid', 6);
    }

    public function testInvalidRawBalanceWithNegativeThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Raw balance must be a numeric string');

        new TokenBalance('-1000', 6);
    }

    public function testInvalidRawBalanceWithDecimalThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Raw balance must be a numeric string');

        new TokenBalance('1000.5', 6);
    }

    public function testNegativeDecimalsThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Decimals must be between 0 and 18');

        new TokenBalance('1000000', -1);
    }

    public function testDecimalsTooLargeThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Decimals must be between 0 and 18');

        new TokenBalance('1000000', 19);
    }

    public function testPrecisionPreservation(): void
    {
        // 测试大额精度保持
        $largeBalance = TokenBalance::fromRaw('999999999999999999', 18);
        $this->assertSame('999999999999999999', $largeBalance->getRaw());
        $this->assertSame('0.999999999999999999', $largeBalance->getScaled());
    }

    public function testZeroDecimalsToken(): void
    {
        $balance = TokenBalance::fromRaw('100', 0);
        $this->assertSame('100', $balance->getRaw());
        $this->assertSame('100', $balance->getScaled());
        $this->assertEqualsWithDelta(100.0, $balance->toFloat(), 0.000001);
    }

    public function testMaxDecimalsToken(): void
    {
        $balance = TokenBalance::fromRaw('1000000000000000000', 18);
        $this->assertSame('1000000000000000000', $balance->getRaw());
        $this->assertSame('1.000000000000000000', $balance->getScaled());
    }

    public function testFromScaledRoundingDown(): void
    {
        // 测试向下舍入 - fromScaled 会缓存传入的值
        $balance = TokenBalance::fromScaled('1.9999999', 6);
        $this->assertSame('1999999', $balance->getRaw());
        $this->assertSame('1.9999999', $balance->getScaled());
    }

    public function testVerySmallAmount(): void
    {
        $balance = TokenBalance::fromRaw('1', 18);
        $this->assertSame('1', $balance->getRaw());
        $this->assertSame('0.000000000000000001', $balance->getScaled());
    }

    public function testCommonUSDTScenario(): void
    {
        // USDT 常见场景：6位小数 - fromScaled 缓存原始输入
        $balance = TokenBalance::fromScaled('100.50', 6);
        $this->assertSame('100500000', $balance->getRaw());
        $this->assertSame('100.50', $balance->getScaled());
        $this->assertSame('100.50 USDT', $balance->format('USDT'));
    }

    public function testCommonWETHScenario(): void
    {
        // WETH 常见场景：18位小数 - fromScaled 缓存原始输入
        $balance = TokenBalance::fromScaled('1.5', 18);
        $this->assertSame('1500000000000000000', $balance->getRaw());
        $this->assertSame('1.5', $balance->getScaled());
        $this->assertSame('1.5 WETH', $balance->format('WETH'));
    }
}
