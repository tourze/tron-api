<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Tests\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\TronAPI\Exception\InvalidArgumentException;
use Tourze\TronAPI\ValueObject\TokenMetadata;

/**
 * @internal
 */
#[CoversClass(TokenMetadata::class)]
class TokenMetadataTest extends TestCase
{
    public function testConstructorWithValidData(): void
    {
        $metadata = new TokenMetadata(
            name: 'Tether USD',
            symbol: 'USDT',
            decimals: 6,
            totalSupply: '1000000000000000'
        );

        $this->assertSame('Tether USD', $metadata->name);
        $this->assertSame('USDT', $metadata->symbol);
        $this->assertSame(6, $metadata->decimals);
        $this->assertSame('1000000000000000', $metadata->totalSupply);
    }

    public function testConstructorThrowsExceptionForEmptyName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Token name cannot be empty');

        new TokenMetadata(
            name: '',
            symbol: 'USDT',
            decimals: 6,
            totalSupply: '1000000'
        );
    }

    public function testConstructorThrowsExceptionForEmptySymbol(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Token symbol cannot be empty');

        new TokenMetadata(
            name: 'Tether USD',
            symbol: '',
            decimals: 6,
            totalSupply: '1000000'
        );
    }

    public function testConstructorThrowsExceptionForNegativeDecimals(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Decimals must be between 0 and 18');

        new TokenMetadata(
            name: 'Tether USD',
            symbol: 'USDT',
            decimals: -1,
            totalSupply: '1000000'
        );
    }

    public function testConstructorThrowsExceptionForDecimalsAbove18(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Decimals must be between 0 and 18');

        new TokenMetadata(
            name: 'Tether USD',
            symbol: 'USDT',
            decimals: 19,
            totalSupply: '1000000'
        );
    }

    public function testConstructorThrowsExceptionForInvalidTotalSupply(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Total supply must be a numeric string');

        new TokenMetadata(
            name: 'Tether USD',
            symbol: 'USDT',
            decimals: 6,
            totalSupply: 'invalid'
        );
    }

    public function testFromArrayWithCompleteData(): void
    {
        $data = [
            'name' => 'Tether USD',
            'symbol' => 'USDT',
            'decimals' => 6,
            'totalSupply' => '1000000000000000',
        ];

        $metadata = TokenMetadata::fromArray($data);

        $this->assertSame('Tether USD', $metadata->name);
        $this->assertSame('USDT', $metadata->symbol);
        $this->assertSame(6, $metadata->decimals);
        $this->assertSame('1000000000000000', $metadata->totalSupply);
    }

    public function testFromArrayWithMissingFields(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $data = [
            'name' => 'Test Token',
        ];

        TokenMetadata::fromArray($data);
    }

    public function testFromArrayTrimsStringValues(): void
    {
        $data = [
            'name' => '  Tether USD  ',
            'symbol' => '  USDT  ',
            'decimals' => '6',
            'totalSupply' => '1000000',
        ];

        $metadata = TokenMetadata::fromArray($data);

        $this->assertSame('Tether USD', $metadata->name);
        $this->assertSame('USDT', $metadata->symbol);
    }

    public function testFromArrayConvertsNumericDecimals(): void
    {
        $data = [
            'name' => 'Tether USD',
            'symbol' => 'USDT',
            'decimals' => '18',
            'totalSupply' => '1000000',
        ];

        $metadata = TokenMetadata::fromArray($data);

        $this->assertSame(18, $metadata->decimals);
    }

    public function testFromArrayThrowsExceptionForNonConvertibleString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Value must be convertible to string');

        $data = [
            'name' => ['invalid'],
            'symbol' => 'USDT',
            'decimals' => 6,
            'totalSupply' => '1000000',
        ];

        TokenMetadata::fromArray($data);
    }

    public function testFromArrayThrowsExceptionForNonConvertibleInt(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Value must be convertible to integer');

        $data = [
            'name' => 'Tether USD',
            'symbol' => 'USDT',
            'decimals' => 'invalid',
            'totalSupply' => '1000000',
        ];

        TokenMetadata::fromArray($data);
    }

    public function testToArray(): void
    {
        $metadata = new TokenMetadata(
            name: 'Tether USD',
            symbol: 'USDT',
            decimals: 6,
            totalSupply: '1000000000000000'
        );

        $array = $metadata->toArray();

        $this->assertSame([
            'name' => 'Tether USD',
            'symbol' => 'USDT',
            'decimals' => 6,
            'totalSupply' => '1000000000000000',
        ], $array);
    }

    public function testGetFullName(): void
    {
        $metadata = new TokenMetadata(
            name: 'Tether USD',
            symbol: 'USDT',
            decimals: 6,
            totalSupply: '1000000'
        );

        $this->assertSame('Tether USD (USDT)', $metadata->getFullName());
    }

    public function testGetFormattedTotalSupply(): void
    {
        $metadata = new TokenMetadata(
            name: 'Tether USD',
            symbol: 'USDT',
            decimals: 6,
            totalSupply: '1000000000000'
        );

        $formatted = $metadata->getFormattedTotalSupply();

        $this->assertSame('1000000.000000 USDT', $formatted);
    }

    public function testIsStandardPrecisionReturnsTrueFor18Decimals(): void
    {
        $metadata = new TokenMetadata(
            name: 'Test Token',
            symbol: 'TEST',
            decimals: 18,
            totalSupply: '1000000'
        );

        $this->assertTrue($metadata->isStandardPrecision());
    }

    public function testIsStandardPrecisionReturnsFalseForNon18Decimals(): void
    {
        $metadata = new TokenMetadata(
            name: 'Tether USD',
            symbol: 'USDT',
            decimals: 6,
            totalSupply: '1000000'
        );

        $this->assertFalse($metadata->isStandardPrecision());
    }

    public function testIsSameTokenReturnsTrueForMatchingSymbols(): void
    {
        $metadata1 = new TokenMetadata(
            name: 'Tether USD',
            symbol: 'USDT',
            decimals: 6,
            totalSupply: '1000000'
        );

        $metadata2 = new TokenMetadata(
            name: 'Tether',
            symbol: 'USDT',
            decimals: 18,
            totalSupply: '2000000'
        );

        $this->assertTrue($metadata1->isSameToken($metadata2));
    }

    public function testIsSameTokenReturnsFalseForDifferentSymbols(): void
    {
        $metadata1 = new TokenMetadata(
            name: 'Tether USD',
            symbol: 'USDT',
            decimals: 6,
            totalSupply: '1000000'
        );

        $metadata2 = new TokenMetadata(
            name: 'Test Token',
            symbol: 'TEST',
            decimals: 6,
            totalSupply: '1000000'
        );

        $this->assertFalse($metadata1->isSameToken($metadata2));
    }

    public function testZeroDecimalsToken(): void
    {
        $metadata = new TokenMetadata(
            name: 'Zero Decimal Token',
            symbol: 'ZERO',
            decimals: 0,
            totalSupply: '1000000'
        );

        $this->assertSame(0, $metadata->decimals);
        $this->assertFalse($metadata->isStandardPrecision());
    }

    public function testMaxDecimalsToken(): void
    {
        $metadata = new TokenMetadata(
            name: 'Max Decimal Token',
            symbol: 'MAX',
            decimals: 18,
            totalSupply: '1000000'
        );

        $this->assertSame(18, $metadata->decimals);
        $this->assertTrue($metadata->isStandardPrecision());
    }

    public function testLargeTotalSupply(): void
    {
        $largeSupply = '999999999999999999999999999999';

        $metadata = new TokenMetadata(
            name: 'Large Supply Token',
            symbol: 'LARGE',
            decimals: 18,
            totalSupply: $largeSupply
        );

        $this->assertSame($largeSupply, $metadata->totalSupply);
    }
}
