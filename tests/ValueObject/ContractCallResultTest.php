<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Tests\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\TronAPI\ValueObject\ContractCallResult;

/**
 * @internal
 */
#[CoversClass(ContractCallResult::class)]
class ContractCallResultTest extends TestCase
{
    public function testCanBeCreatedFromMinimalData(): void
    {
        $data = [];
        $result = ContractCallResult::fromArray($data);

        $this->assertInstanceOf(ContractCallResult::class, $result);
        $this->assertFalse($result->isSuccess());
        $this->assertSame([], $result->getDecodedOutputs());
        $this->assertNull($result->getConstantResult());
        $this->assertNull($result->getEnergyUsed());
    }

    public function testCanBeCreatedFromSuccessfulConstantCall(): void
    {
        $data = [
            'constant_result' => ['0000000000000000000000000000000000000000000000000000000000000001'],
            'Energy_used' => 1000,
            '0' => true,
        ];

        $result = ContractCallResult::fromArray($data);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('0000000000000000000000000000000000000000000000000000000000000001', $result->getConstantResult());
        $this->assertSame(1000, $result->getEnergyUsed());
        $this->assertTrue($result->getFirstOutput());
    }

    public function testCanBeCreatedFromSuccessfulSmartContractCall(): void
    {
        $data = [
            'result' => [
                'result' => true,
            ],
            'energy_used' => 2500,
            '0' => 'some_value',
            '1' => 123,
            'transaction' => [
                'txID' => 'abc123',
            ],
        ];

        $result = ContractCallResult::fromArray($data);

        $this->assertTrue($result->isSuccess());
        $this->assertSame(2500, $result->getEnergyUsed());
        $this->assertSame('some_value', $result->getOutput(0));
        $this->assertSame(123, $result->getOutput(1));
        $this->assertSame('abc123', $result->getTransactionHash());
    }

    public function testGetFirstOutputReturnsFirstDecodedOutput(): void
    {
        $data = [
            '0' => 'first',
            '1' => 'second',
            'constant_result' => ['abc'],
        ];

        $result = ContractCallResult::fromArray($data);

        $this->assertSame('first', $result->getFirstOutput());
    }

    public function testGetOutputReturnsNullForNonExistentIndex(): void
    {
        $data = [
            '0' => 'value',
        ];

        $result = ContractCallResult::fromArray($data);

        $this->assertNull($result->getOutput(5));
    }

    public function testToArrayReturnsOriginalData(): void
    {
        $data = [
            'result' => ['result' => true],
            '0' => 'test',
            'Energy_used' => 1000,
        ];

        $result = ContractCallResult::fromArray($data);

        $this->assertSame($data, $result->toArray());
    }

    public function testGetRawFieldReturnsSpecificField(): void
    {
        $data = [
            'custom_field' => 'custom_value',
            '0' => 'test',
        ];

        $result = ContractCallResult::fromArray($data);

        $this->assertSame('custom_value', $result->getRawField('custom_field'));
        $this->assertNull($result->getRawField('non_existent'));
    }
}
