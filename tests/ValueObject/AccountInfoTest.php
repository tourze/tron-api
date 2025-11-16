<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Tests\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\TronAPI\Exception\InvalidArgumentException;
use Tourze\TronAPI\Exception\RuntimeException;
use Tourze\TronAPI\ValueObject\AccountInfo;

/**
 * @internal
 */
#[CoversClass(AccountInfo::class)]
class AccountInfoTest extends TestCase
{
    public function testCanBeCreatedFromMinimalData(): void
    {
        $data = [];
        $accountInfo = AccountInfo::fromArray($data);

        $this->assertInstanceOf(AccountInfo::class, $accountInfo);
        $this->assertSame('', $accountInfo->getAddress());
        $this->assertSame(0, $accountInfo->getBalance());
        $this->assertSame([], $accountInfo->getAssetV2());
        $this->assertTrue($accountInfo->isEmpty());
    }

    public function testCanBeCreatedFromCompleteData(): void
    {
        $data = [
            'address' => '41a7d8a35b260395c14aa456297662092ba3b76fc0',
            'balance' => 1000000000,
            'assetV2' => [
                ['key' => '1000001', 'value' => 500],
                ['key' => '1000002', 'value' => '1000'],
            ],
            'create_time' => 1234567890,
            'latest_opration_time' => 1234567900,
        ];

        $accountInfo = AccountInfo::fromArray($data);

        $this->assertSame('41a7d8a35b260395c14aa456297662092ba3b76fc0', $accountInfo->getAddress());
        $this->assertSame(1000000000, $accountInfo->getBalance());
        $this->assertCount(2, $accountInfo->getAssetV2());
        $this->assertFalse($accountInfo->isEmpty());
    }

    public function testGetAssetV2ReturnsNormalizedData(): void
    {
        $data = [
            'assetV2' => [
                ['key' => 1000001, 'value' => 500],  // numeric key
                ['key' => '1000002', 'value' => '1000'],  // string key
            ],
        ];

        $accountInfo = AccountInfo::fromArray($data);
        $assetV2 = $accountInfo->getAssetV2();

        $this->assertCount(2, $assetV2);
        $this->assertSame('1000001', $assetV2[0]['key']);
        $this->assertSame(500, $assetV2[0]['value']);
        $this->assertSame('1000002', $assetV2[1]['key']);
        $this->assertSame('1000', $assetV2[1]['value']);
    }

    public function testFindAssetBalanceReturnsCorrectValue(): void
    {
        $data = [
            'assetV2' => [
                ['key' => '1000001', 'value' => 500],
                ['key' => '1000002', 'value' => 1000],
            ],
        ];

        $accountInfo = AccountInfo::fromArray($data);

        $this->assertSame(500, $accountInfo->findAssetBalance(1000001));
        $this->assertSame(1000, $accountInfo->findAssetBalance(1000002));
        $this->assertNull($accountInfo->findAssetBalance(1000003));
    }

    public function testIsEmptyReturnsTrueForEmptyAccount(): void
    {
        $data = [
            'address' => '41a7d8a35b260395c14aa456297662092ba3b76fc0',
            'balance' => 0,
        ];

        $accountInfo = AccountInfo::fromArray($data);
        $this->assertTrue($accountInfo->isEmpty());
    }

    public function testIsEmptyReturnsFalseWhenHasBalance(): void
    {
        $data = [
            'balance' => 1000000,
        ];

        $accountInfo = AccountInfo::fromArray($data);
        $this->assertFalse($accountInfo->isEmpty());
    }

    public function testIsEmptyReturnsFalseWhenHasAssets(): void
    {
        $data = [
            'balance' => 0,
            'assetV2' => [
                ['key' => '1000001', 'value' => 500],
            ],
        ];

        $accountInfo = AccountInfo::fromArray($data);
        $this->assertFalse($accountInfo->isEmpty());
    }

    public function testGetRawFieldReturnsCorrectValue(): void
    {
        $data = [
            'address' => '41a7d8a35b260395c14aa456297662092ba3b76fc0',
            'balance' => 1000000,
            'create_time' => 1234567890,
            'custom_field' => 'custom_value',
        ];

        $accountInfo = AccountInfo::fromArray($data);

        $this->assertSame(1234567890, $accountInfo->getRawField('create_time'));
        $this->assertSame('custom_value', $accountInfo->getRawField('custom_field'));
        $this->assertNull($accountInfo->getRawField('non_existent'));
    }

    public function testToArrayReturnsOriginalData(): void
    {
        $data = [
            'address' => '41a7d8a35b260395c14aa456297662092ba3b76fc0',
            'balance' => 1000000,
            'assetV2' => [
                ['key' => '1000001', 'value' => 500],
            ],
            'create_time' => 1234567890,
        ];

        $accountInfo = AccountInfo::fromArray($data);
        $arrayData = $accountInfo->toArray();

        $this->assertSame($data, $arrayData);
    }

    public function testThrowsExceptionForInvalidAddressType(): void
    {
        $data = [
            'address' => 123,  // invalid type
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Account address must be a string');

        AccountInfo::fromArray($data);
    }

    public function testThrowsExceptionForInvalidBalanceType(): void
    {
        $data = [
            'balance' => 'invalid',  // non-numeric string
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Account balance must be numeric');

        AccountInfo::fromArray($data);
    }

    public function testThrowsExceptionForInvalidAssetV2Type(): void
    {
        $data = [
            'assetV2' => 'invalid',  // not an array
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Account assetV2 must be an array');

        AccountInfo::fromArray($data);
    }

    public function testSkipsInvalidAssetItems(): void
    {
        $data = [
            'assetV2' => [
                ['key' => '1000001', 'value' => 500],  // valid
                'invalid_item',  // invalid: not an array
                ['value' => 1000],  // invalid: missing key (will be skipped)
                ['key' => [], 'value' => 500],  // invalid: key is array
                ['key' => '1000003', 'value' => []],  // invalid: value is array
                ['key' => '1000004', 'value' => 1500],  // valid
            ],
        ];

        $accountInfo = AccountInfo::fromArray($data);
        $assetV2 = $accountInfo->getAssetV2();

        // Only 2 valid items should be kept
        $this->assertCount(2, $assetV2);
        $this->assertSame('1000001', $assetV2[0]['key']);
        $this->assertSame('1000004', $assetV2[1]['key']);
    }

    public function testThrowsExceptionWhenAssetHasKeyButNoValue(): void
    {
        $data = [
            'assetV2' => [
                ['key' => '1000002'],  // invalid: missing value
            ],
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid token value structure');

        AccountInfo::fromArray($data);
    }

    public function testHandlesNumericBalance(): void
    {
        $data = [
            'balance' => '500000000',  // numeric string
        ];

        $accountInfo = AccountInfo::fromArray($data);
        $this->assertSame(500000000, $accountInfo->getBalance());
    }
}
