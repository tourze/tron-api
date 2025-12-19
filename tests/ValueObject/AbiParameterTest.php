<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Tests\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\TronAPI\Exception\InvalidArgumentException;
use Tourze\TronAPI\ValueObject\AbiParameter;

#[CoversClass(AbiParameter::class)]
final class AbiParameterTest extends TestCase
{
    public function testConstructorWithValidType(): void
    {
        $parameter = new AbiParameter(type: 'address');

        self::assertSame('address', $parameter->type);
        self::assertNull($parameter->name);
        self::assertFalse($parameter->indexed);
    }

    public function testConstructorWithAllParameters(): void
    {
        $parameter = new AbiParameter(
            type: 'uint256',
            name: 'value',
            indexed: true
        );

        self::assertSame('uint256', $parameter->type);
        self::assertSame('value', $parameter->name);
        self::assertTrue($parameter->indexed);
    }

    public function testConstructorThrowsExceptionForEmptyType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ABI parameter type cannot be empty');

        new AbiParameter(type: '');
    }

    public function testConstructorThrowsExceptionForWhitespaceType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ABI parameter type cannot be empty');

        new AbiParameter(type: '   ');
    }

    public function testFromArrayWithMinimalData(): void
    {
        $data = ['type' => 'string'];

        $parameter = AbiParameter::fromArray($data);

        self::assertSame('string', $parameter->type);
        self::assertNull($parameter->name);
        self::assertFalse($parameter->indexed);
    }

    public function testFromArrayWithFullData(): void
    {
        $data = [
            'type' => 'bytes32',
            'name' => 'hash',
            'indexed' => true,
        ];

        $parameter = AbiParameter::fromArray($data);

        self::assertSame('bytes32', $parameter->type);
        self::assertSame('hash', $parameter->name);
        self::assertTrue($parameter->indexed);
    }

    public function testFromArrayWithIndexedFalse(): void
    {
        $data = [
            'type' => 'address',
            'indexed' => false,
        ];

        $parameter = AbiParameter::fromArray($data);

        self::assertSame('address', $parameter->type);
        self::assertFalse($parameter->indexed);
    }

    public function testFromArrayWithNonBooleanIndexed(): void
    {
        $data = [
            'type' => 'uint256',
            'indexed' => 1,
        ];

        $parameter = AbiParameter::fromArray($data);

        self::assertTrue($parameter->indexed);
    }

    public function testFromArrayThrowsExceptionWhenTypeMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ABI parameter must have a string type field');

        AbiParameter::fromArray(['name' => 'value']);
    }

    public function testFromArrayThrowsExceptionWhenTypeIsNotString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ABI parameter must have a string type field');

        AbiParameter::fromArray(['type' => 123]);
    }

    public function testFromArrayIgnoresNonStringName(): void
    {
        $data = [
            'type' => 'address',
            'name' => 123,
        ];

        $parameter = AbiParameter::fromArray($data);

        self::assertNull($parameter->name);
    }

    public function testToArrayWithMinimalParameter(): void
    {
        $parameter = new AbiParameter(type: 'address');

        $array = $parameter->toArray();

        self::assertSame(['type' => 'address'], $array);
    }

    public function testToArrayWithName(): void
    {
        $parameter = new AbiParameter(
            type: 'uint256',
            name: 'amount'
        );

        $array = $parameter->toArray();

        self::assertSame([
            'type' => 'uint256',
            'name' => 'amount',
        ], $array);
    }

    public function testToArrayWithIndexed(): void
    {
        $parameter = new AbiParameter(
            type: 'address',
            indexed: true
        );

        $array = $parameter->toArray();

        self::assertSame([
            'type' => 'address',
            'indexed' => true,
        ], $array);
    }

    public function testToArrayWithAllParameters(): void
    {
        $parameter = new AbiParameter(
            type: 'bytes32',
            name: 'hash',
            indexed: true
        );

        $array = $parameter->toArray();

        self::assertSame([
            'type' => 'bytes32',
            'name' => 'hash',
            'indexed' => true,
        ], $array);
    }

    public function testToArrayOmitsIndexedWhenFalse(): void
    {
        $parameter = new AbiParameter(
            type: 'string',
            name: 'message',
            indexed: false
        );

        $array = $parameter->toArray();

        self::assertSame([
            'type' => 'string',
            'name' => 'message',
        ], $array);
        self::assertArrayNotHasKey('indexed', $array);
    }

    public function testGetType(): void
    {
        $parameter = new AbiParameter(type: 'uint256');

        self::assertSame('uint256', $parameter->getType());
    }

    public function testGetNameReturnsNullWhenNotSet(): void
    {
        $parameter = new AbiParameter(type: 'address');

        self::assertNull($parameter->getName());
    }

    public function testGetNameReturnsValue(): void
    {
        $parameter = new AbiParameter(
            type: 'string',
            name: 'description'
        );

        self::assertSame('description', $parameter->getName());
    }

    public function testIsIndexedReturnsFalseByDefault(): void
    {
        $parameter = new AbiParameter(type: 'address');

        self::assertFalse($parameter->isIndexed());
    }

    public function testIsIndexedReturnsTrue(): void
    {
        $parameter = new AbiParameter(
            type: 'address',
            indexed: true
        );

        self::assertTrue($parameter->isIndexed());
    }

    public function testRoundTripFromArrayToArray(): void
    {
        $originalData = [
            'type' => 'uint256',
            'name' => 'balance',
            'indexed' => true,
        ];

        $parameter = AbiParameter::fromArray($originalData);
        $resultData = $parameter->toArray();

        self::assertSame($originalData, $resultData);
    }

    public function testComplexTypeSupport(): void
    {
        $parameter = new AbiParameter(type: 'tuple[]');

        self::assertSame('tuple[]', $parameter->getType());
    }
}
