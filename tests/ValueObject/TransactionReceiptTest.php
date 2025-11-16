<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Tests\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\TronAPI\ValueObject\TransactionReceipt;

/**
 * @internal
 */
#[CoversClass(TransactionReceipt::class)]
class TransactionReceiptTest extends TestCase
{
    public function testCanBeCreatedFromMinimalData(): void
    {
        $data = [];
        $receipt = TransactionReceipt::fromArray($data);

        $this->assertInstanceOf(TransactionReceipt::class, $receipt);
        $this->assertSame('', $receipt->getId());
        $this->assertSame(0, $receipt->getBlockNumber());
        $this->assertSame(0, $receipt->getBlockTimestamp());
        $this->assertFalse($receipt->isSuccess());
    }

    public function testCanBeCreatedFromCompleteData(): void
    {
        $data = [
            'id' => 'abc123',
            'blockNumber' => 12345,
            'blockTimeStamp' => 1609459200000,
            'contractResult' => ['0000000000000000000000000000000000000000000000000000000000000001'],
            'contract_address' => '41a7d8a35b260395c14aa456297662092ba3b76fc0',
            'receipt' => [
                'result' => 'SUCCESS',
            ],
            'fee' => 1000000,
            'energy_fee' => 500000,
            'energy_usage' => 25000,
            'net_fee' => 100000,
            'net_usage' => 200,
            'log' => [
                ['address' => 'test'],
            ],
        ];

        $receipt = TransactionReceipt::fromArray($data);

        $this->assertSame('abc123', $receipt->getId());
        $this->assertSame(12345, $receipt->getBlockNumber());
        $this->assertSame(1609459200000, $receipt->getBlockTimestamp());
        $this->assertSame('0000000000000000000000000000000000000000000000000000000000000001', $receipt->getContractResult());
        $this->assertSame('41a7d8a35b260395c14aa456297662092ba3b76fc0', $receipt->getContractAddress());
        $this->assertTrue($receipt->isSuccess());
        $this->assertSame(1000000, $receipt->getFee());
        $this->assertSame(500000, $receipt->getEnergyFee());
        $this->assertSame(25000, $receipt->getEnergyUsage());
        $this->assertSame(100000, $receipt->getNetFee());
        $this->assertSame(200, $receipt->getNetUsage());
        $this->assertCount(1, $receipt->getLogs());
    }

    public function testContractResultCanBeArrayOrString(): void
    {
        $dataWithArray = [
            'contractResult' => ['abc', 'def'],
        ];

        $receipt1 = TransactionReceipt::fromArray($dataWithArray);
        $this->assertSame('abc', $receipt1->getContractResult());

        $dataWithString = [
            'contractResult' => 'xyz',
        ];

        $receipt2 = TransactionReceipt::fromArray($dataWithString);
        $this->assertSame('xyz', $receipt2->getContractResult());
    }

    public function testIsSuccessReturnsTrueOnlyForSuccessReceipt(): void
    {
        $successData = [
            'receipt' => ['result' => 'SUCCESS'],
        ];

        $receipt1 = TransactionReceipt::fromArray($successData);
        $this->assertTrue($receipt1->isSuccess());

        $failedData = [
            'receipt' => ['result' => 'FAILED'],
        ];

        $receipt2 = TransactionReceipt::fromArray($failedData);
        $this->assertFalse($receipt2->isSuccess());
    }

    public function testGetLogsReturnsEmptyArrayWhenNoLogs(): void
    {
        $data = [];
        $receipt = TransactionReceipt::fromArray($data);

        $this->assertSame([], $receipt->getLogs());
    }

    public function testToArrayReturnsOriginalData(): void
    {
        $data = [
            'id' => 'test',
            'blockNumber' => 100,
            'fee' => 1000,
        ];

        $receipt = TransactionReceipt::fromArray($data);

        $this->assertSame($data, $receipt->toArray());
    }
}
