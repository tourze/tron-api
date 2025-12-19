<?php

namespace Tourze\TronAPI\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\TronAPI\Exception\InvalidArgumentException;
use Tourze\TronAPI\Service\EncodingService;

/**
 * @internal
 */
#[CoversClass(EncodingService::class)]
class EncodingServiceTest extends TestCase
{
    private EncodingService $encodingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->encodingService = new EncodingService();
    }

    public function testCanBeInstantiated(): void
    {
        $this->assertInstanceOf(EncodingService::class, $this->encodingService);
    }

    // toHex() tests
    public function testToHexConvertsStringCorrectly(): void
    {
        $input = 'test';
        $result = $this->encodingService->toHex($input);

        $this->assertIsString($result);
        $this->assertStringStartsWith('0x', $result);
        $this->assertSame('0x74657374', $result);
    }

    public function testToHexWithEmptyString(): void
    {
        $result = $this->encodingService->toHex('');

        $this->assertSame('0x', $result);
    }

    // fromHex() tests
    public function testFromHexConvertsHexStringCorrectly(): void
    {
        $hex = '0x74657374';
        $result = $this->encodingService->fromHex($hex);

        $this->assertIsString($result);
        $this->assertSame('test', $result);
    }

    public function testFromHexWithoutPrefix(): void
    {
        $hex = '74657374';
        $result = $this->encodingService->fromHex($hex);

        $this->assertSame('test', $result);
    }

    public function testFromHexWithInvalidHex(): void
    {
        $hex = '0xgg';
        $result = $this->encodingService->fromHex($hex);

        $this->assertSame('', $result);
    }

    // stringUtf8toHex() tests
    public function testStringUtf8toHexConvertsStringCorrectly(): void
    {
        $input = 'test';
        $result = $this->encodingService->stringUtf8toHex($input);

        $this->assertIsString($result);
        $this->assertSame('74657374', $result);
    }

    public function testStringUtf8toHexWithEmptyString(): void
    {
        $result = $this->encodingService->stringUtf8toHex('');

        $this->assertSame('', $result);
    }

    public function testStringUtf8toHexWithUtf8Characters(): void
    {
        $input = 'hello世界';
        $result = $this->encodingService->stringUtf8toHex($input);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
        // Verify it's valid hex
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/i', $result);
    }

    // hexString2Utf8() tests
    public function testHexString2Utf8ConvertsHexCorrectly(): void
    {
        $hex = '74657374';
        $result = $this->encodingService->hexString2Utf8($hex);

        $this->assertSame('test', $result);
    }

    public function testHexString2Utf8WithInvalidHex(): void
    {
        $hex = 'gg';
        $result = $this->encodingService->hexString2Utf8($hex);

        $this->assertSame('', $result);
    }

    // trxToSun() tests - 将 TRX 转换为 SUN（类型安全 API）
    public function testTrxToSunConvertsAmountCorrectly(): void
    {
        $result = $this->encodingService->trxToSun('1');

        $this->assertSame('1000000', $result->getSun());
    }

    public function testTrxToSunWithDecimalAmount(): void
    {
        $result = $this->encodingService->trxToSun('1.5');

        $this->assertSame('1500000', $result->getSun());
    }

    public function testTrxToSunWithZeroAmount(): void
    {
        $result = $this->encodingService->trxToSun('0');

        $this->assertSame('0', $result->getSun());
    }

    public function testTrxToSunWithLargeAmount(): void
    {
        $result = $this->encodingService->trxToSun('1000');

        $this->assertSame('1000000000', $result->getSun());
    }

    // sunToTrx() tests - 将 SUN 转换为 TRX（类型安全 API）
    public function testSunToTrxConvertsAmountCorrectly(): void
    {
        $result = $this->encodingService->sunToTrx('1000000');

        $this->assertSame('1.000000', $result->getTrx());
    }

    public function testSunToTrxWithZeroAmount(): void
    {
        $result = $this->encodingService->sunToTrx('0');

        $this->assertSame('0.000000', $result->getTrx());
    }

    public function testSunToTrxWithLargeAmount(): void
    {
        $result = $this->encodingService->sunToTrx('1000000000');

        $this->assertSame('1000.000000', $result->getTrx());
    }

    // address2HexString() tests
    public function testAddress2HexStringThrowsExceptionForNullAddress(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Address cannot be null or empty');

        $this->encodingService->address2HexString(null);
    }

    public function testAddress2HexStringThrowsExceptionForEmptyAddress(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Address cannot be null or empty');

        $this->encodingService->address2HexString('');
    }

    public function testAddress2HexStringReturnsHexAddressUnchanged(): void
    {
        $hexAddress = '41' . str_repeat('a', 40);
        $result = $this->encodingService->address2HexString($hexAddress);

        $this->assertSame($hexAddress, $result);
    }

    public function testAddress2HexStringConvertsBase58Address(): void
    {
        // Valid Base58 address (34 characters)
        $base58Address = str_repeat('T', 34);
        $result = $this->encodingService->address2HexString($base58Address);

        $this->assertIsString($result);
        $this->assertStringStartsWith('41', $result);
        $this->assertSame(42, strlen($result));
    }

    public function testAddress2HexStringThrowsExceptionForInvalidBase58Length(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid address length');

        $invalidAddress = 'TooShort';
        $this->encodingService->address2HexString($invalidAddress);
    }

    // Round-trip conversion tests
    public function testToHexAndFromHexRoundTrip(): void
    {
        $original = 'test string';
        $hex = $this->encodingService->toHex($original);
        $decoded = $this->encodingService->fromHex($hex);

        $this->assertSame($original, $decoded);
    }

    public function testStringUtf8toHexAndHexString2Utf8RoundTrip(): void
    {
        $original = 'test string';
        $hex = $this->encodingService->stringUtf8toHex($original);
        $decoded = $this->encodingService->hexString2Utf8($hex);

        $this->assertSame($original, $decoded);
    }

    public function testTrxToSunAndSunToTrxRoundTrip(): void
    {
        $original = '100';
        $sun = $this->encodingService->trxToSun($original);
        $trxAmount = $this->encodingService->sunToTrx($sun->getSun());

        $this->assertSame('100.000000', $trxAmount->getTrx());
    }

    public function testTrxToSunAndSunToTrxRoundTripWithDecimals(): void
    {
        $original = '123.456789';
        $sun = $this->encodingService->trxToSun($original);
        $trxAmount = $this->encodingService->sunToTrx($sun->getSun());

        // TronAmount 保留 6 位小数精度
        $this->assertSame('123.456789', $trxAmount->getTrx());
    }
}
