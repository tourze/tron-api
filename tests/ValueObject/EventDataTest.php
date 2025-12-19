<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Tests\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\TronAPI\Exception\InvalidArgumentException;
use Tourze\TronAPI\ValueObject\EventData;

/**
 * @internal
 */
#[CoversClass(EventData::class)]
class EventDataTest extends TestCase
{
    public function testConstructorWithMinimalData(): void
    {
        $event = new EventData(
            eventName: 'Transfer',
            contractAddress: 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            blockNumber: 12345,
            timestamp: 1609459200000,
            result: ['from' => 'address1', 'to' => 'address2']
        );

        $this->assertSame('Transfer', $event->eventName);
        $this->assertSame('TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t', $event->contractAddress);
        $this->assertSame(12345, $event->blockNumber);
        $this->assertSame(1609459200000, $event->timestamp);
        $this->assertSame(['from' => 'address1', 'to' => 'address2'], $event->result);
        $this->assertNull($event->transactionId);
    }

    public function testConstructorWithTransactionId(): void
    {
        $txId = '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef';
        $event = new EventData(
            eventName: 'Transfer',
            contractAddress: 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            blockNumber: 12345,
            timestamp: 1609459200000,
            result: [],
            transactionId: $txId
        );

        $this->assertSame($txId, $event->transactionId);
    }

    public function testConstructorThrowsExceptionForNegativeBlockNumber(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Block number must be non-negative');

        new EventData(
            eventName: 'Transfer',
            contractAddress: 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            blockNumber: -1,
            timestamp: 1609459200000,
            result: []
        );
    }

    public function testConstructorThrowsExceptionForNegativeTimestamp(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Timestamp must be non-negative');

        new EventData(
            eventName: 'Transfer',
            contractAddress: 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            blockNumber: 12345,
            timestamp: -1,
            result: []
        );
    }

    public function testConstructorThrowsExceptionForInvalidTransactionId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid transaction ID format');

        new EventData(
            eventName: 'Transfer',
            contractAddress: 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            blockNumber: 12345,
            timestamp: 1609459200000,
            result: [],
            transactionId: 'invalid-transaction-id'
        );
    }

    public function testFromArrayWithEventNameFormat(): void
    {
        $data = [
            'event_name' => 'Transfer',
            'contract_address' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            'block_number' => 12345,
            'timestamp' => 1609459200000,
            'result' => ['from' => 'addr1', 'to' => 'addr2'],
            'transaction_id' => '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef',
        ];

        $event = EventData::fromArray($data);

        $this->assertSame('Transfer', $event->eventName);
        $this->assertSame('TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t', $event->contractAddress);
        $this->assertSame(12345, $event->blockNumber);
        $this->assertSame(1609459200000, $event->timestamp);
        $this->assertSame(['from' => 'addr1', 'to' => 'addr2'], $event->result);
        $this->assertSame('1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef', $event->transactionId);
    }

    public function testFromArrayWithCamelCaseFormat(): void
    {
        $data = [
            'eventName' => 'Approval',
            'contractAddress' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            'blockNumber' => 54321,
            'block_timestamp' => 1609459200000,
            'result' => ['owner' => 'addr1', 'spender' => 'addr2'],
            'transactionId' => 'abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890',
        ];

        $event = EventData::fromArray($data);

        $this->assertSame('Approval', $event->eventName);
        $this->assertSame('TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t', $event->contractAddress);
        $this->assertSame(54321, $event->blockNumber);
        $this->assertSame(1609459200000, $event->timestamp);
    }

    public function testFromArrayWithAlternativeFieldNames(): void
    {
        $data = [
            'event' => 'Transfer',
            'address' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            'block' => 99999,
            'time' => 1609459200000,
            'result' => ['value' => '1000'],
        ];

        $event = EventData::fromArray($data);

        $this->assertSame('Transfer', $event->eventName);
        $this->assertSame('TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t', $event->contractAddress);
        $this->assertSame(99999, $event->blockNumber);
        $this->assertSame(1609459200000, $event->timestamp);
    }

    public function testFromArrayWithMissingFields(): void
    {
        $data = [
            'result' => ['data' => 'value'],
        ];

        $event = EventData::fromArray($data);

        $this->assertSame('', $event->eventName);
        $this->assertSame('', $event->contractAddress);
        $this->assertSame(0, $event->blockNumber);
        $this->assertSame(0, $event->timestamp);
        $this->assertNull($event->transactionId);
    }

    public function testFromArrayWithNonArrayResult(): void
    {
        $data = [
            'event_name' => 'Transfer',
            'contract_address' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            'block_number' => 12345,
            'timestamp' => 1609459200000,
            'result' => 'not an array',
        ];

        $event = EventData::fromArray($data);

        $this->assertSame([], $event->result);
    }

    public function testFromArrayWithNumericValues(): void
    {
        $data = [
            'event_name' => 123,
            'contract_address' => 456,
            'block_number' => '78900',
            'timestamp' => '1609459200000',
            'result' => [],
        ];

        $event = EventData::fromArray($data);

        $this->assertSame('123', $event->eventName);
        $this->assertSame('456', $event->contractAddress);
        $this->assertSame(78900, $event->blockNumber);
        $this->assertSame(1609459200000, $event->timestamp);
    }

    public function testFromArrayBatch(): void
    {
        $dataList = [
            [
                'event_name' => 'Transfer',
                'contract_address' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
                'block_number' => 12345,
                'timestamp' => 1609459200000,
                'result' => ['from' => 'addr1'],
            ],
            [
                'event_name' => 'Approval',
                'contract_address' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
                'block_number' => 12346,
                'timestamp' => 1609459200001,
                'result' => ['owner' => 'addr2'],
            ],
        ];

        $events = EventData::fromArrayBatch($dataList);

        $this->assertCount(2, $events);
        $this->assertInstanceOf(EventData::class, $events[0]);
        $this->assertInstanceOf(EventData::class, $events[1]);
        $this->assertSame('Transfer', $events[0]->eventName);
        $this->assertSame('Approval', $events[1]->eventName);
    }

    public function testToArrayWithAllFields(): void
    {
        $event = new EventData(
            eventName: 'Transfer',
            contractAddress: 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            blockNumber: 12345,
            timestamp: 1609459200000,
            result: ['from' => 'addr1', 'to' => 'addr2'],
            transactionId: '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef'
        );

        $array = $event->toArray();

        $this->assertSame('Transfer', $array['event']);
        $this->assertSame('TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t', $array['contract_address']);
        $this->assertSame(12345, $array['block_number']);
        $this->assertSame(1609459200000, $array['timestamp']);
        $this->assertSame(['from' => 'addr1', 'to' => 'addr2'], $array['result']);
        $this->assertSame('1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef', $array['transaction_id']);
    }

    public function testToArrayWithEmptyValues(): void
    {
        $event = new EventData(
            eventName: '',
            contractAddress: '',
            blockNumber: 0,
            timestamp: 0,
            result: []
        );

        $array = $event->toArray();

        $this->assertArrayNotHasKey('event', $array);
        $this->assertArrayNotHasKey('contract_address', $array);
        $this->assertArrayNotHasKey('block_number', $array);
        $this->assertArrayNotHasKey('timestamp', $array);
        $this->assertArrayHasKey('result', $array);
        $this->assertSame([], $array['result']);
        $this->assertArrayNotHasKey('transaction_id', $array);
    }

    public function testToArrayWithEmptyTransactionId(): void
    {
        $event = new EventData(
            eventName: 'Transfer',
            contractAddress: 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            blockNumber: 12345,
            timestamp: 1609459200000,
            result: [],
            transactionId: ''
        );

        $array = $event->toArray();

        $this->assertArrayNotHasKey('transaction_id', $array);
    }

    public function testGetResultField(): void
    {
        $event = new EventData(
            eventName: 'Transfer',
            contractAddress: 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            blockNumber: 12345,
            timestamp: 1609459200000,
            result: ['from' => 'addr1', 'to' => 'addr2', 'value' => 1000]
        );

        $this->assertSame('addr1', $event->getResultField('from'));
        $this->assertSame('addr2', $event->getResultField('to'));
        $this->assertSame(1000, $event->getResultField('value'));
        $this->assertNull($event->getResultField('nonexistent'));
    }

    public function testHasResultField(): void
    {
        $event = new EventData(
            eventName: 'Transfer',
            contractAddress: 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            blockNumber: 12345,
            timestamp: 1609459200000,
            result: ['from' => 'addr1', 'to' => null]
        );

        $this->assertTrue($event->hasResultField('from'));
        $this->assertTrue($event->hasResultField('to'));
        $this->assertFalse($event->hasResultField('nonexistent'));
    }

    public function testGetFormattedTimestamp(): void
    {
        $event = new EventData(
            eventName: 'Transfer',
            contractAddress: 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            blockNumber: 12345,
            timestamp: 1609459200000,
            result: []
        );

        $formatted = $event->getFormattedTimestamp();

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $formatted);
        $this->assertStringContainsString('2021-01-01', $formatted);
    }

    public function testIsSameEventWithTransactionId(): void
    {
        $txId = '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef';
        $event1 = new EventData(
            eventName: 'Transfer',
            contractAddress: 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            blockNumber: 12345,
            timestamp: 1609459200000,
            result: [],
            transactionId: $txId
        );

        $event2 = new EventData(
            eventName: 'Transfer',
            contractAddress: 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            blockNumber: 99999,
            timestamp: 9999999999999,
            result: [],
            transactionId: $txId
        );

        $this->assertTrue($event1->isSameEvent($event2));
    }

    public function testIsSameEventWithDifferentTransactionId(): void
    {
        $event1 = new EventData(
            eventName: 'Transfer',
            contractAddress: 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            blockNumber: 12345,
            timestamp: 1609459200000,
            result: [],
            transactionId: '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef'
        );

        $event2 = new EventData(
            eventName: 'Transfer',
            contractAddress: 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            blockNumber: 12345,
            timestamp: 1609459200000,
            result: [],
            transactionId: 'abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890'
        );

        $this->assertFalse($event1->isSameEvent($event2));
    }

    public function testIsSameEventWithDifferentEventName(): void
    {
        $txId = '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef';
        $event1 = new EventData(
            eventName: 'Transfer',
            contractAddress: 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            blockNumber: 12345,
            timestamp: 1609459200000,
            result: [],
            transactionId: $txId
        );

        $event2 = new EventData(
            eventName: 'Approval',
            contractAddress: 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            blockNumber: 12345,
            timestamp: 1609459200000,
            result: [],
            transactionId: $txId
        );

        $this->assertFalse($event1->isSameEvent($event2));
    }

    public function testIsSameEventWithoutTransactionIdSameBlock(): void
    {
        $event1 = new EventData(
            eventName: 'Transfer',
            contractAddress: 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            blockNumber: 12345,
            timestamp: 1609459200000,
            result: []
        );

        $event2 = new EventData(
            eventName: 'Transfer',
            contractAddress: 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            blockNumber: 12345,
            timestamp: 1609459200000,
            result: []
        );

        $this->assertTrue($event1->isSameEvent($event2));
    }

    public function testIsSameEventWithoutTransactionIdDifferentBlock(): void
    {
        $event1 = new EventData(
            eventName: 'Transfer',
            contractAddress: 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            blockNumber: 12345,
            timestamp: 1609459200000,
            result: []
        );

        $event2 = new EventData(
            eventName: 'Transfer',
            contractAddress: 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            blockNumber: 12346,
            timestamp: 1609459200000,
            result: []
        );

        $this->assertFalse($event1->isSameEvent($event2));
    }

    public function testIsSameEventWithoutTransactionIdDifferentTimestamp(): void
    {
        $event1 = new EventData(
            eventName: 'Transfer',
            contractAddress: 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            blockNumber: 12345,
            timestamp: 1609459200000,
            result: []
        );

        $event2 = new EventData(
            eventName: 'Transfer',
            contractAddress: 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            blockNumber: 12345,
            timestamp: 1609459200001,
            result: []
        );

        $this->assertFalse($event1->isSameEvent($event2));
    }

    public function testIsSameEventWithoutTransactionIdDifferentContract(): void
    {
        $event1 = new EventData(
            eventName: 'Transfer',
            contractAddress: 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            blockNumber: 12345,
            timestamp: 1609459200000,
            result: []
        );

        $event2 = new EventData(
            eventName: 'Transfer',
            contractAddress: 'TSSMHYeV2uE9qYH95DqyoCuNCzEL1NvU3S',
            blockNumber: 12345,
            timestamp: 1609459200000,
            result: []
        );

        $this->assertFalse($event1->isSameEvent($event2));
    }

    public function testConstructorAcceptsEmptyEventName(): void
    {
        $event = new EventData(
            eventName: '',
            contractAddress: 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            blockNumber: 12345,
            timestamp: 1609459200000,
            result: []
        );

        $this->assertSame('', $event->eventName);
    }

    public function testConstructorAcceptsEmptyContractAddress(): void
    {
        $event = new EventData(
            eventName: 'Transfer',
            contractAddress: '',
            blockNumber: 12345,
            timestamp: 1609459200000,
            result: []
        );

        $this->assertSame('', $event->contractAddress);
    }

    public function testFromArrayTrimsStringValues(): void
    {
        $data = [
            'event_name' => '  Transfer  ',
            'contract_address' => '  TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t  ',
            'block_number' => 12345,
            'timestamp' => 1609459200000,
            'result' => [],
        ];

        $event = EventData::fromArray($data);

        $this->assertSame('Transfer', $event->eventName);
        $this->assertSame('TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t', $event->contractAddress);
    }

    public function testConstructorAcceptsValidTransactionIdUpperCase(): void
    {
        $txId = 'ABCDEF1234567890ABCDEF1234567890ABCDEF1234567890ABCDEF1234567890';
        $event = new EventData(
            eventName: 'Transfer',
            contractAddress: 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            blockNumber: 12345,
            timestamp: 1609459200000,
            result: [],
            transactionId: $txId
        );

        $this->assertSame($txId, $event->transactionId);
    }

    public function testConstructorAcceptsValidTransactionIdMixedCase(): void
    {
        $txId = 'AbCdEf1234567890aBcDeF1234567890AbCdEf1234567890aBcDeF1234567890';
        $event = new EventData(
            eventName: 'Transfer',
            contractAddress: 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            blockNumber: 12345,
            timestamp: 1609459200000,
            result: [],
            transactionId: $txId
        );

        $this->assertSame($txId, $event->transactionId);
    }

    public function testFromArrayThrowsExceptionForNonConvertibleString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Value must be convertible to string');

        $data = [
            'event_name' => ['not', 'a', 'string'],
            'contract_address' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            'block_number' => 12345,
            'timestamp' => 1609459200000,
            'result' => [],
        ];

        EventData::fromArray($data);
    }

    public function testFromArrayThrowsExceptionForNonConvertibleInt(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Value must be convertible to integer');

        $data = [
            'event_name' => 'Transfer',
            'contract_address' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            'block_number' => 'not a number',
            'timestamp' => 1609459200000,
            'result' => [],
        ];

        EventData::fromArray($data);
    }
}
