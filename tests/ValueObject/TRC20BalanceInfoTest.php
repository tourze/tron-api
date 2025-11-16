<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Tests\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\TronAPI\ValueObject\TRC20BalanceInfo;

/**
 * @internal
 */
#[CoversClass(TRC20BalanceInfo::class)]
class TRC20BalanceInfoTest extends TestCase
{
    public function testCanBeCreatedFromMinimalData(): void
    {
        $data = [];
        $balanceInfo = TRC20BalanceInfo::fromArray($data);

        $this->assertInstanceOf(TRC20BalanceInfo::class, $balanceInfo);
        $this->assertSame('', $balanceInfo->getName());
        $this->assertSame('', $balanceInfo->getSymbol());
        $this->assertSame(0.0, $balanceInfo->getBalance());
        $this->assertSame('', $balanceInfo->getValue());
        $this->assertSame(0, $balanceInfo->getDecimals());
        $this->assertTrue($balanceInfo->isZero());
    }

    public function testCanBeCreatedFromCompleteData(): void
    {
        $data = [
            'name' => 'Tether USD',
            'symbol' => 'USDT',
            'balance' => 100.5,
            'value' => '100500000',
            'decimals' => 6,
        ];

        $balanceInfo = TRC20BalanceInfo::fromArray($data);

        $this->assertSame('Tether USD', $balanceInfo->getName());
        $this->assertSame('USDT', $balanceInfo->getSymbol());
        $this->assertSame(100.5, $balanceInfo->getBalance());
        $this->assertSame('100500000', $balanceInfo->getValue());
        $this->assertSame(6, $balanceInfo->getDecimals());
        $this->assertFalse($balanceInfo->isZero());
    }

    public function testIsZeroReturnsTrueForZeroBalance(): void
    {
        $data = [
            'balance' => 0.0,
        ];

        $balanceInfo = TRC20BalanceInfo::fromArray($data);

        $this->assertTrue($balanceInfo->isZero());
    }

    public function testIsZeroReturnsTrueForNegativeBalance(): void
    {
        $data = [
            'balance' => -1.0,
        ];

        $balanceInfo = TRC20BalanceInfo::fromArray($data);

        $this->assertTrue($balanceInfo->isZero());
    }

    public function testToArrayReturnsCorrectStructure(): void
    {
        $data = [
            'name' => 'Token',
            'symbol' => 'TKN',
            'balance' => 50.0,
            'value' => '50000000',
            'decimals' => 6,
        ];

        $balanceInfo = TRC20BalanceInfo::fromArray($data);
        $array = $balanceInfo->toArray();

        $this->assertSame($data, $array);
    }

    public function testHandlesNumericStringValues(): void
    {
        $data = [
            'balance' => '123.45',
            'decimals' => '8',
        ];

        $balanceInfo = TRC20BalanceInfo::fromArray($data);

        $this->assertSame(123.45, $balanceInfo->getBalance());
        $this->assertSame(8, $balanceInfo->getDecimals());
    }
}
