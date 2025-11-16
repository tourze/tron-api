<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Tests\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\TronAPI\Exception\InvalidArgumentException;
use Tourze\TronAPI\ValueObject\TronAmount;

/**
 * @internal
 */
#[CoversClass(TronAmount::class)]
class TronAmountTest extends TestCase
{
    public function testFromSun(): void
    {
        $amount = TronAmount::fromSun('1000000');
        $this->assertSame('1000000', $amount->getSun());
        $this->assertSame('1.000000', $amount->getTrx());
    }

    public function testFromTrx(): void
    {
        $amount = TronAmount::fromTrx('1.5');
        $this->assertSame('1500000', $amount->getSun());
        $this->assertSame('1.500000', $amount->getTrx());
    }

    public function testFromSunInt(): void
    {
        $amount = TronAmount::fromSunInt(2500000);
        $this->assertSame('2500000', $amount->getSun());
        $this->assertSame(2500000, $amount->getSunInt());
        $this->assertSame('2.500000', $amount->getTrx());
    }

    public function testFromTrxFloat(): void
    {
        $amount = TronAmount::fromTrxFloat(3.14);
        $this->assertSame('3140000', $amount->getSun());
        $this->assertEqualsWithDelta(3.14, $amount->getTrxFloat(), 0.000001);
    }

    public function testIsZero(): void
    {
        $zero = TronAmount::fromSun('0');
        $this->assertTrue($zero->isZero());

        $nonZero = TronAmount::fromSun('1');
        $this->assertFalse($nonZero->isZero());
    }

    public function testIsPositive(): void
    {
        $positive = TronAmount::fromSun('100');
        $this->assertTrue($positive->isPositive());

        $zero = TronAmount::fromSun('0');
        $this->assertFalse($zero->isPositive());

        $negative = TronAmount::fromSun('-100');
        $this->assertFalse($negative->isPositive());
    }

    public function testIsNegative(): void
    {
        $negative = TronAmount::fromSun('-100');
        $this->assertTrue($negative->isNegative());

        $zero = TronAmount::fromSun('0');
        $this->assertFalse($zero->isNegative());

        $positive = TronAmount::fromSun('100');
        $this->assertFalse($positive->isNegative());
    }

    public function testCompareTo(): void
    {
        $amount1 = TronAmount::fromSun('1000000');
        $amount2 = TronAmount::fromSun('2000000');
        $amount3 = TronAmount::fromSun('1000000');

        $this->assertSame(-1, $amount1->compareTo($amount2));
        $this->assertSame(1, $amount2->compareTo($amount1));
        $this->assertSame(0, $amount1->compareTo($amount3));
    }

    public function testAdd(): void
    {
        $amount1 = TronAmount::fromTrx('1.5');
        $amount2 = TronAmount::fromTrx('2.3');
        $result = $amount1->add($amount2);

        $this->assertSame('3.800000', $result->getTrx());
        $this->assertSame('3800000', $result->getSun());
    }

    public function testSubtract(): void
    {
        $amount1 = TronAmount::fromTrx('5.0');
        $amount2 = TronAmount::fromTrx('2.3');
        $result = $amount1->subtract($amount2);

        $this->assertSame('2.700000', $result->getTrx());
        $this->assertSame('2700000', $result->getSun());
    }

    public function testMultiply(): void
    {
        $amount = TronAmount::fromTrx('2.5');
        $result = $amount->multiply('3');

        $this->assertSame('7.500000', $result->getTrx());
        $this->assertSame('7500000', $result->getSun());
    }

    public function testDivide(): void
    {
        $amount = TronAmount::fromTrx('10.0');
        $result = $amount->divide('4');

        $this->assertSame('2.500000', $result->getTrx());
        $this->assertSame('2500000', $result->getSun());
    }

    public function testDivideByZeroThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot divide by zero');

        $amount = TronAmount::fromTrx('10.0');
        $amount->divide('0');
    }

    public function testFormat(): void
    {
        $amount = TronAmount::fromTrx('1.5');
        $this->assertSame('1.500000 TRX', $amount->format());
        $this->assertSame('1.500000', $amount->format(false));
    }

    public function testToArray(): void
    {
        $amount = TronAmount::fromTrx('1.5');
        $array = $amount->toArray();

        $this->assertSame('1500000', $array['sun']);
        $this->assertSame('1.500000', $array['trx']);
        $this->assertSame(6, $array['decimals']);
    }

    public function testToString(): void
    {
        $amount = TronAmount::fromTrx('1.5');
        $this->assertSame('1.500000', (string) $amount);
    }

    public function testInvalidSunThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SUN amount must be a numeric string');

        new TronAmount('invalid');
    }

    public function testPrecisionPreservation(): void
    {
        // 测试大额精度保持
        $largeAmount = TronAmount::fromSun('999999999999999');
        $this->assertSame('999999999999999', $largeAmount->getSun());
        $this->assertSame('999999999.999999', $largeAmount->getTrx());
    }

    public function testNegativeAmounts(): void
    {
        $negative = TronAmount::fromSun('-1000000');
        $this->assertSame('-1000000', $negative->getSun());
        $this->assertSame('-1.000000', $negative->getTrx());
        $this->assertTrue($negative->isNegative());
    }
}
