<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Tests\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\TronAPI\Exception\InvalidArgumentException;
use Tourze\TronAPI\ValueObject\TransactionResult;

/**
 * @internal
 */
#[CoversClass(TransactionResult::class)]
class TransactionResultTest extends TestCase
{
    public function testCanBeCreatedWithMinimalData(): void
    {
        $result = new TransactionResult(
            txID: 'test_tx_id_12345',
            result: true
        );

        $this->assertInstanceOf(TransactionResult::class, $result);
        $this->assertSame('test_tx_id_12345', $result->txID);
        $this->assertTrue($result->result);
        $this->assertNull($result->message);
        $this->assertNull($result->rawDataHex);
        $this->assertNull($result->signature);
    }

    public function testCanBeCreatedWithAllData(): void
    {
        $signatures = ['sig1', 'sig2', 'sig3'];
        $result = new TransactionResult(
            txID: 'test_tx_id_67890',
            result: false,
            message: 'Transaction failed',
            rawDataHex: '0a0b0c0d',
            signature: $signatures
        );

        $this->assertSame('test_tx_id_67890', $result->txID);
        $this->assertFalse($result->result);
        $this->assertSame('Transaction failed', $result->message);
        $this->assertSame('0a0b0c0d', $result->rawDataHex);
        $this->assertSame($signatures, $result->signature);
    }

    public function testConstructorThrowsExceptionForEmptyTxID(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Transaction ID cannot be empty');

        new TransactionResult(
            txID: '',
            result: true
        );
    }

    public function testFromArrayCreatesInstanceWithMinimalData(): void
    {
        $data = [
            'txID' => 'minimal_tx_id',
        ];

        $result = TransactionResult::fromArray($data);

        $this->assertSame('minimal_tx_id', $result->txID);
        $this->assertFalse($result->result);
        $this->assertNull($result->message);
        $this->assertNull($result->rawDataHex);
        $this->assertNull($result->signature);
    }

    public function testFromArrayCreatesInstanceWithCompleteData(): void
    {
        $data = [
            'txID' => 'complete_tx_id',
            'result' => true,
            'message' => 'Success message',
            'raw_data_hex' => 'abcdef1234567890',
            'signature' => ['signature1', 'signature2'],
        ];

        $result = TransactionResult::fromArray($data);

        $this->assertSame('complete_tx_id', $result->txID);
        $this->assertTrue($result->result);
        $this->assertSame('Success message', $result->message);
        $this->assertSame('abcdef1234567890', $result->rawDataHex);
        $this->assertSame(['signature1', 'signature2'], $result->signature);
    }

    public function testFromArrayThrowsExceptionWhenTxIDMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Transaction ID is required');

        TransactionResult::fromArray([
            'result' => true,
        ]);
    }

    public function testFromArrayThrowsExceptionWhenTxIDNotString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Transaction ID is required');

        TransactionResult::fromArray([
            'txID' => 12345,
            'result' => true,
        ]);
    }

    public function testFromArrayFiltersNonStringSignatures(): void
    {
        $data = [
            'txID' => 'test_tx_id',
            'signature' => ['valid_sig', 123, 'another_valid_sig', null, true],
        ];

        $result = TransactionResult::fromArray($data);

        // 只有字符串类型的签名会被保留
        $this->assertSame(['valid_sig', 'another_valid_sig'], $result->signature);
    }

    public function testFromArrayHandlesEmptySignatureArray(): void
    {
        $data = [
            'txID' => 'test_tx_id',
            'signature' => [],
        ];

        $result = TransactionResult::fromArray($data);

        $this->assertSame([], $result->signature);
    }

    public function testFromArrayHandlesNonArraySignature(): void
    {
        $data = [
            'txID' => 'test_tx_id',
            'signature' => 'not_an_array',
        ];

        $result = TransactionResult::fromArray($data);

        $this->assertNull($result->signature);
    }

    public function testFromArrayConvertsResultToBool(): void
    {
        // 测试各种布尔值表示形式
        $testCases = [
            [true, true],
            [false, false],
            [1, true],
            [0, false],
            ['1', true],
            ['0', false],
            ['true', true],
            ['false', false],
        ];

        foreach ($testCases as [$input, $expected]) {
            $data = [
                'txID' => 'test_tx_id',
                'result' => $input,
            ];

            $result = TransactionResult::fromArray($data);
            $this->assertSame($expected, $result->result);
        }
    }

    public function testFromArrayConvertsMessageToString(): void
    {
        $testCases = [
            ['string message', 'string message'],
            [123, '123'],
            [45.67, '45.67'],
            [true, '1'],
            [false, ''],
        ];

        foreach ($testCases as [$input, $expected]) {
            $data = [
                'txID' => 'test_tx_id',
                'message' => $input,
            ];

            $result = TransactionResult::fromArray($data);
            $this->assertSame($expected, $result->message);
        }
    }

    public function testFromArrayConvertsRawDataHexToString(): void
    {
        $data = [
            'txID' => 'test_tx_id',
            'raw_data_hex' => 12345,
        ];

        $result = TransactionResult::fromArray($data);
        $this->assertSame('12345', $result->rawDataHex);
    }

    public function testFromArrayThrowsExceptionForInvalidBooleanValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Value must be convertible to boolean');

        TransactionResult::fromArray([
            'txID' => 'test_tx_id',
            'result' => 'invalid_bool',
        ]);
    }

    public function testFromArrayThrowsExceptionForNonScalarMessage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Value must be convertible to string');

        TransactionResult::fromArray([
            'txID' => 'test_tx_id',
            'message' => ['array', 'value'],
        ]);
    }

    public function testFromArrayThrowsExceptionForObjectMessage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Value must be convertible to string');

        TransactionResult::fromArray([
            'txID' => 'test_tx_id',
            'message' => new \stdClass(),
        ]);
    }

    public function testToArrayReturnsMinimalData(): void
    {
        $result = new TransactionResult(
            txID: 'test_tx_id',
            result: true
        );

        $expected = [
            'txID' => 'test_tx_id',
            'result' => true,
        ];

        $this->assertSame($expected, $result->toArray());
    }

    public function testToArrayReturnsCompleteData(): void
    {
        $result = new TransactionResult(
            txID: 'test_tx_id',
            result: false,
            message: 'Error occurred',
            rawDataHex: 'deadbeef',
            signature: ['sig1', 'sig2']
        );

        $expected = [
            'txID' => 'test_tx_id',
            'result' => false,
            'message' => 'Error occurred',
            'raw_data_hex' => 'deadbeef',
            'signature' => ['sig1', 'sig2'],
        ];

        $this->assertSame($expected, $result->toArray());
    }

    public function testToArrayOmitsNullValues(): void
    {
        $result = new TransactionResult(
            txID: 'test_tx_id',
            result: true,
            message: null,
            rawDataHex: 'abc123',
            signature: null
        );

        $array = $result->toArray();

        $this->assertArrayHasKey('txID', $array);
        $this->assertArrayHasKey('result', $array);
        $this->assertArrayHasKey('raw_data_hex', $array);
        $this->assertArrayNotHasKey('message', $array);
        $this->assertArrayNotHasKey('signature', $array);
    }

    public function testIsSuccessfulReturnsTrueForSuccessfulTransaction(): void
    {
        $result = new TransactionResult(
            txID: 'test_tx_id',
            result: true
        );

        $this->assertTrue($result->isSuccessful());
    }

    public function testIsSuccessfulReturnsFalseForFailedTransaction(): void
    {
        $result = new TransactionResult(
            txID: 'test_tx_id',
            result: false
        );

        $this->assertFalse($result->isSuccessful());
    }

    public function testGetErrorMessageReturnsNullForSuccessfulTransaction(): void
    {
        $result = new TransactionResult(
            txID: 'test_tx_id',
            result: true,
            message: 'This should not be returned'
        );

        $this->assertNull($result->getErrorMessage());
    }

    public function testGetErrorMessageReturnsMessageForFailedTransaction(): void
    {
        $result = new TransactionResult(
            txID: 'test_tx_id',
            result: false,
            message: 'Transaction failed due to insufficient balance'
        );

        $this->assertSame('Transaction failed due to insufficient balance', $result->getErrorMessage());
    }

    public function testGetErrorMessageReturnsNullWhenNoMessageProvided(): void
    {
        $result = new TransactionResult(
            txID: 'test_tx_id',
            result: false
        );

        $this->assertNull($result->getErrorMessage());
    }

    public function testRoundTripThroughArrayConversion(): void
    {
        $original = [
            'txID' => 'roundtrip_tx_id',
            'result' => true,
            'message' => 'Success',
            'raw_data_hex' => 'fedcba9876543210',
            'signature' => ['signature_a', 'signature_b', 'signature_c'],
        ];

        $result = TransactionResult::fromArray($original);
        $converted = $result->toArray();

        $this->assertSame($original, $converted);
    }

    public function testReadonlyPropertiesCannotBeModified(): void
    {
        $result = new TransactionResult(
            txID: 'test_tx_id',
            result: true
        );

        // 使用反射验证 readonly 属性在运行时的行为
        $property = new \ReflectionProperty(TransactionResult::class, 'txID');
        $this->assertTrue($property->isReadOnly(), 'Property txID should be readonly');

        // 尝试通过反射修改 readonly 属性会抛出错误
        $this->expectException(\Error::class);
        $this->expectExceptionMessageMatches('/Cannot modify readonly property/');
        $property->setValue($result, 'modified_tx_id');
    }
}
