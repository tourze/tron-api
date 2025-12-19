<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Tests\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tourze\TronAPI\Exception\InvalidArgumentException;
use Tourze\TronAPI\ValueObject\AddressValidation;

#[CoversClass(AddressValidation::class)]
class AddressValidationTest extends TestCase
{
    /**
     * 测试从包含完整数据的数组创建实例
     */
    public function testFromArrayWithCompleteData(): void
    {
        $data = [
            'result' => true,
            'message' => 'Valid address',
            'extra_field' => 'extra_value',
        ];

        $validation = AddressValidation::fromArray($data);

        $this->assertTrue($validation->isValid());
        $this->assertSame('Valid address', $validation->getMessage());
        $this->assertSame($data, $validation->toArray());
    }

    /**
     * 测试从只包含必需字段的数组创建实例
     */
    public function testFromArrayWithMinimalData(): void
    {
        $data = ['result' => true];

        $validation = AddressValidation::fromArray($data);

        $this->assertTrue($validation->isValid());
        $this->assertSame('', $validation->getMessage());
        $this->assertSame($data, $validation->toArray());
    }

    /**
     * 测试从包含 false 结果的数组创建实例
     */
    public function testFromArrayWithFalseResult(): void
    {
        $data = [
            'result' => false,
            'message' => 'Invalid address format',
        ];

        $validation = AddressValidation::fromArray($data);

        $this->assertFalse($validation->isValid());
        $this->assertSame('Invalid address format', $validation->getMessage());
    }

    /**
     * 测试 result 字段的类型转换
     */
    #[DataProvider('resultValueProvider')]
    public function testResultTypeConversion(mixed $resultValue, bool $expectedResult): void
    {
        $data = ['result' => $resultValue];

        $validation = AddressValidation::fromArray($data);

        $this->assertSame($expectedResult, $validation->isValid());
    }

    /**
     * @return array<string, array{resultValue: mixed, expectedResult: bool}>
     */
    public static function resultValueProvider(): array
    {
        return [
            'boolean true' => ['resultValue' => true, 'expectedResult' => true],
            'boolean false' => ['resultValue' => false, 'expectedResult' => false],
            'integer 1' => ['resultValue' => 1, 'expectedResult' => true],
            'integer 0' => ['resultValue' => 0, 'expectedResult' => false],
            'string non-empty' => ['resultValue' => 'yes', 'expectedResult' => true],
            'string empty' => ['resultValue' => '', 'expectedResult' => false],
            'array non-empty' => ['resultValue' => ['data'], 'expectedResult' => true],
            'array empty' => ['resultValue' => [], 'expectedResult' => false],
        ];
    }

    /**
     * 测试缺少 result 字段时抛出异常
     */
    public function testFromArrayThrowsExceptionWhenResultMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Validation result is required');

        AddressValidation::fromArray(['message' => 'Some message']);
    }

    /**
     * 测试空数组抛出异常
     */
    public function testFromArrayThrowsExceptionWithEmptyArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Validation result is required');

        AddressValidation::fromArray([]);
    }

    /**
     * 测试 message 字段为非字符串类型时使用默认空字符串
     */
    #[DataProvider('nonStringMessageProvider')]
    public function testMessageDefaultsToEmptyStringForNonStringValues(mixed $messageValue): void
    {
        $data = [
            'result' => true,
            'message' => $messageValue,
        ];

        $validation = AddressValidation::fromArray($data);

        $this->assertSame('', $validation->getMessage());
    }

    /**
     * @return array<string, array{messageValue: mixed}>
     */
    public static function nonStringMessageProvider(): array
    {
        return [
            'integer' => ['messageValue' => 123],
            'boolean' => ['messageValue' => true],
            'array' => ['messageValue' => ['error']],
            'null' => ['messageValue' => null],
        ];
    }

    /**
     * 测试 message 字段不存在时使用默认空字符串
     */
    public function testMessageDefaultsToEmptyStringWhenMissing(): void
    {
        $data = ['result' => true];

        $validation = AddressValidation::fromArray($data);

        $this->assertSame('', $validation->getMessage());
    }

    /**
     * 测试获取存在的原始字段
     */
    public function testGetRawFieldReturnsExistingField(): void
    {
        $data = [
            'result' => true,
            'message' => 'Success',
            'custom_field' => 'custom_value',
            'nested' => ['key' => 'value'],
        ];

        $validation = AddressValidation::fromArray($data);

        $this->assertTrue($validation->getRawField('result'));
        $this->assertSame('Success', $validation->getRawField('message'));
        $this->assertSame('custom_value', $validation->getRawField('custom_field'));
        $this->assertSame(['key' => 'value'], $validation->getRawField('nested'));
    }

    /**
     * 测试获取不存在的原始字段返回 null
     */
    public function testGetRawFieldReturnsNullForNonExistentField(): void
    {
        $data = ['result' => true];

        $validation = AddressValidation::fromArray($data);

        $this->assertNull($validation->getRawField('nonexistent'));
        $this->assertNull($validation->getRawField('message'));
    }

    /**
     * 测试 toArray 返回完整原始数据
     */
    public function testToArrayReturnsCompleteRawData(): void
    {
        $data = [
            'result' => true,
            'message' => 'Valid',
            'timestamp' => 1234567890,
            'metadata' => ['key' => 'value'],
        ];

        $validation = AddressValidation::fromArray($data);

        $this->assertSame($data, $validation->toArray());
    }

    /**
     * 测试 isValid 方法正确反映验证结果
     */
    public function testIsValidReflectsValidationResult(): void
    {
        $validData = ['result' => true];
        $invalidData = ['result' => false];

        $validValidation = AddressValidation::fromArray($validData);
        $invalidValidation = AddressValidation::fromArray($invalidData);

        $this->assertTrue($validValidation->isValid());
        $this->assertFalse($invalidValidation->isValid());
    }

    /**
     * 测试对象不可变性 - toArray 修改不影响原始对象
     */
    public function testImmutabilityOfToArray(): void
    {
        $data = ['result' => true, 'message' => 'Original'];

        $validation = AddressValidation::fromArray($data);
        $array = $validation->toArray();
        $array['message'] = 'Modified';

        // 验证原始对象未被修改
        $this->assertSame('Original', $validation->getMessage());
        $this->assertSame($data, $validation->toArray());
    }

    /**
     * 测试处理包含特殊字符的消息
     */
    public function testMessageWithSpecialCharacters(): void
    {
        $specialMessage = "Invalid: '\"<>&\n\t地址无效";
        $data = [
            'result' => false,
            'message' => $specialMessage,
        ];

        $validation = AddressValidation::fromArray($data);

        $this->assertSame($specialMessage, $validation->getMessage());
    }

    /**
     * 测试处理大型原始数据数组
     */
    public function testHandlesLargeRawDataArray(): void
    {
        $data = ['result' => true];
        for ($i = 0; $i < 1000; $i++) {
            $data["field_$i"] = "value_$i";
        }

        $validation = AddressValidation::fromArray($data);

        $this->assertTrue($validation->isValid());
        $this->assertSame('value_500', $validation->getRawField('field_500'));
        $this->assertCount(1001, $validation->toArray());
    }
}
