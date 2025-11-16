<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Tests\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\TronAPI\Exception\InvalidArgumentException;
use Tourze\TronAPI\ValueObject\BlockInfo;

/**
 * @internal
 */
#[CoversClass(BlockInfo::class)]
class BlockInfoTest extends TestCase
{
    public function testCanBeCreatedFromCompleteData(): void
    {
        $data = [
            'blockID' => '0000000000000001f6f2f0b0f8f4f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0',
            'block_header' => [
                'raw_data' => [
                    'number' => 123456,
                    'timestamp' => 1234567890000,
                    'txTrieRoot' => '0x...',
                ],
                'witness_signature' => '0x...',
            ],
            'transactions' => [
                ['txID' => 'tx1', 'raw_data' => []],
                ['txID' => 'tx2', 'raw_data' => []],
            ],
        ];

        $blockInfo = BlockInfo::fromArray($data);

        $this->assertInstanceOf(BlockInfo::class, $blockInfo);
        $this->assertSame('0000000000000001f6f2f0b0f8f4f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0', $blockInfo->getBlockID());
        $this->assertSame(123456, $blockInfo->getBlockNumber());
        $this->assertSame(1234567890000, $blockInfo->getTimestamp());
        $this->assertCount(2, $blockInfo->getTransactions());
        $this->assertTrue($blockInfo->hasTransactions());
        $this->assertSame(2, $blockInfo->getTransactionCount());
    }

    public function testCanBeCreatedWithMinimalData(): void
    {
        $data = [
            'blockID' => '0000000000000001abc',
        ];

        $blockInfo = BlockInfo::fromArray($data);

        $this->assertSame('0000000000000001abc', $blockInfo->getBlockID());
        $this->assertSame(0, $blockInfo->getBlockNumber());
        $this->assertSame(0, $blockInfo->getTimestamp());
        $this->assertSame([], $blockInfo->getTransactions());
        $this->assertFalse($blockInfo->hasTransactions());
        $this->assertSame(0, $blockInfo->getTransactionCount());
    }

    public function testThrowsExceptionWhenBlockIDMissing(): void
    {
        $data = [
            'block_header' => [
                'raw_data' => [
                    'number' => 123456,
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Block ID is required');

        BlockInfo::fromArray($data);
    }

    public function testThrowsExceptionWhenBlockIDNotString(): void
    {
        $data = [
            'blockID' => 123456,  // not a string
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Block ID must be a string');

        BlockInfo::fromArray($data);
    }

    public function testGetTransactionReturnsCorrectTransaction(): void
    {
        $data = [
            'blockID' => '0000000000000001abc',
            'transactions' => [
                ['txID' => 'tx1', 'amount' => 100],
                ['txID' => 'tx2', 'amount' => 200],
                ['txID' => 'tx3', 'amount' => 300],
            ],
        ];

        $blockInfo = BlockInfo::fromArray($data);

        $tx0 = $blockInfo->getTransaction(0);
        $this->assertIsArray($tx0);
        $this->assertSame('tx1', $tx0['txID']);
        $this->assertSame(100, $tx0['amount']);

        $tx2 = $blockInfo->getTransaction(2);
        $this->assertIsArray($tx2);
        $this->assertSame('tx3', $tx2['txID']);
    }

    public function testGetTransactionReturnsNullForInvalidIndex(): void
    {
        $data = [
            'blockID' => '0000000000000001abc',
            'transactions' => [
                ['txID' => 'tx1'],
            ],
        ];

        $blockInfo = BlockInfo::fromArray($data);

        $this->assertNull($blockInfo->getTransaction(-1));
        $this->assertNull($blockInfo->getTransaction(1));
        $this->assertNull($blockInfo->getTransaction(100));
    }

    public function testGetBlockHeaderReturnsCorrectData(): void
    {
        $data = [
            'blockID' => '0000000000000001abc',
            'block_header' => [
                'raw_data' => [
                    'number' => 123456,
                    'timestamp' => 1234567890000,
                ],
                'witness_signature' => '0xabcdef',
            ],
        ];

        $blockInfo = BlockInfo::fromArray($data);
        $header = $blockInfo->getBlockHeader();

        $this->assertIsArray($header);
        $this->assertArrayHasKey('raw_data', $header);
        $this->assertArrayHasKey('witness_signature', $header);
        $this->assertSame('0xabcdef', $header['witness_signature']);
    }

    public function testGetRawFieldReturnsCorrectValue(): void
    {
        $data = [
            'blockID' => '0000000000000001abc',
            'custom_field' => 'custom_value',
            'size' => 12345,
        ];

        $blockInfo = BlockInfo::fromArray($data);

        $this->assertSame('custom_value', $blockInfo->getRawField('custom_field'));
        $this->assertSame(12345, $blockInfo->getRawField('size'));
        $this->assertNull($blockInfo->getRawField('non_existent'));
    }

    public function testToArrayReturnsOriginalData(): void
    {
        $data = [
            'blockID' => '0000000000000001abc',
            'block_header' => [
                'raw_data' => [
                    'number' => 123456,
                    'timestamp' => 1234567890000,
                ],
            ],
            'transactions' => [
                ['txID' => 'tx1'],
            ],
            'custom_field' => 'value',
        ];

        $blockInfo = BlockInfo::fromArray($data);
        $arrayData = $blockInfo->toArray();

        $this->assertSame($data, $arrayData);
    }

    public function testFiltersNonArrayTransactions(): void
    {
        $data = [
            'blockID' => '0000000000000001abc',
            'transactions' => [
                ['txID' => 'tx1'],  // valid
                'invalid_string',  // invalid
                123,  // invalid
                ['txID' => 'tx2'],  // valid
                null,  // invalid
            ],
        ];

        $blockInfo = BlockInfo::fromArray($data);
        $transactions = $blockInfo->getTransactions();

        // Only valid array items should be kept
        $this->assertCount(2, $transactions);
        $this->assertSame('tx1', $transactions[0]['txID']);
        $this->assertSame('tx2', $transactions[1]['txID']);
    }

    public function testHandlesNumericBlockNumberAndTimestamp(): void
    {
        $data = [
            'blockID' => '0000000000000001abc',
            'block_header' => [
                'raw_data' => [
                    'number' => '123456',  // string number
                    'timestamp' => '1234567890000',  // string timestamp
                ],
            ],
        ];

        $blockInfo = BlockInfo::fromArray($data);

        $this->assertSame(123456, $blockInfo->getBlockNumber());
        $this->assertSame(1234567890000, $blockInfo->getTimestamp());
    }

    public function testHandlesMissingBlockHeader(): void
    {
        $data = [
            'blockID' => '0000000000000001abc',
            // no block_header
        ];

        $blockInfo = BlockInfo::fromArray($data);

        $this->assertSame(0, $blockInfo->getBlockNumber());
        $this->assertSame(0, $blockInfo->getTimestamp());
        $this->assertSame([], $blockInfo->getBlockHeader());
    }

    public function testHandlesMissingRawDataInBlockHeader(): void
    {
        $data = [
            'blockID' => '0000000000000001abc',
            'block_header' => [
                'witness_signature' => '0xabc',
                // no raw_data
            ],
        ];

        $blockInfo = BlockInfo::fromArray($data);

        $this->assertSame(0, $blockInfo->getBlockNumber());
        $this->assertSame(0, $blockInfo->getTimestamp());
    }
}
