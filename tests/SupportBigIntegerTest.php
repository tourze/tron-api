<?php

namespace Tourze\TronAPI\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\TronAPI\Support\BigInteger;

/**
 * @internal
 */
#[CoversClass(BigInteger::class)]
class SupportBigIntegerTest extends TestCase
{
    public function testClassCanBeInstantiated(): void
    {
        $bigInt = new BigInteger();
        $this->assertInstanceOf(BigInteger::class, $bigInt);
        $this->assertSame('0', $bigInt->getValue());
    }

    public function testAdd(): void
    {
        $bigInt = new BigInteger('100');
        $result = $bigInt->add('50');
        $this->assertSame('150', $result->getValue());
    }

    public function testSubtract(): void
    {
        $bigInt = new BigInteger('100');
        $result = $bigInt->subtract('30');
        $this->assertSame('70', $result->getValue());
    }

    public function testMultiply(): void
    {
        $bigInt = new BigInteger('10');
        $result = $bigInt->multiply('5');
        $this->assertSame('50', $result->getValue());
    }

    public function testDivide(): void
    {
        $bigInt = new BigInteger('100');
        $result = $bigInt->divide('4');
        $this->assertSame('25', $result->getValue());
    }

    public function testMod(): void
    {
        $bigInt = new BigInteger('17');
        $result = $bigInt->mod('5');
        $this->assertSame('2', $result->getValue());
    }

    public function testPow(): void
    {
        $bigInt = new BigInteger('2');
        $result = $bigInt->pow(10);
        $this->assertSame('1024', $result->getValue());
    }

    public function testAbs(): void
    {
        $bigInt = new BigInteger('-100');
        $result = $bigInt->abs();
        $this->assertSame('100', $result->getValue());
    }

    public function testNegate(): void
    {
        $bigInt = new BigInteger('100');
        $result = $bigInt->negate();
        $this->assertSame('-100', $result->getValue());
    }

    public function testCmp(): void
    {
        $bigInt = new BigInteger('100');
        $this->assertSame(1, $bigInt->cmp('50'));
        $this->assertSame(0, $bigInt->cmp('100'));
        $this->assertSame(-1, $bigInt->cmp('200'));
    }

    public function testFactorial(): void
    {
        $bigInt = new BigInteger('5');
        $result = $bigInt->factorial();
        $this->assertSame('120', $result->getValue());
    }

    public function testToString(): void
    {
        $bigInt = new BigInteger('12345');
        $this->assertSame('12345', $bigInt->toString());
        $this->assertSame('12345', (string) $bigInt);
    }
}
