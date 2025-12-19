<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Tests\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\TronAPI\Exception\InvalidArgumentException;
use Tourze\TronAPI\ValueObject\TokenOptions;

/**
 * @internal
 */
#[CoversClass(TokenOptions::class)]
class TokenOptionsTest extends TestCase
{
    /**
     * @return array{name: string, abbreviation: string, totalSupply: int, trxRatio: int, saleStart: int, saleEnd: int, description: string, url: string, freeBandwidth: int, freeBandwidthLimit: int, frozenAmount: int, frozenDuration: int}
     */
    private function getValidOptions(): array
    {
        return [
            'name' => 'Test Token',
            'abbreviation' => 'TTK',
            'totalSupply' => 1000000,
            'trxRatio' => 10,
            'saleStart' => time() + 3600,
            'saleEnd' => time() + 7200,
            'description' => 'A test token for unit testing',
            'url' => 'https://example.com',
            'freeBandwidth' => 1000,
            'freeBandwidthLimit' => 5000,
            'frozenAmount' => 100,
            'frozenDuration' => 3,
        ];
    }

    public function testConstructorWithValidData(): void
    {
        $options = $this->getValidOptions();

        $tokenOptions = new TokenOptions(
            name: $options['name'],
            abbreviation: $options['abbreviation'],
            totalSupply: $options['totalSupply'],
            trxRatio: $options['trxRatio'],
            saleStart: $options['saleStart'],
            saleEnd: $options['saleEnd'],
            description: $options['description'],
            url: $options['url'],
            freeBandwidth: $options['freeBandwidth'],
            freeBandwidthLimit: $options['freeBandwidthLimit'],
            frozenAmount: $options['frozenAmount'],
            frozenDuration: $options['frozenDuration'],
        );

        $this->assertSame($options['name'], $tokenOptions->name);
        $this->assertSame($options['abbreviation'], $tokenOptions->abbreviation);
        $this->assertSame($options['totalSupply'], $tokenOptions->totalSupply);
        $this->assertSame($options['trxRatio'], $tokenOptions->trxRatio);
        $this->assertSame($options['saleStart'], $tokenOptions->saleStart);
        $this->assertSame($options['saleEnd'], $tokenOptions->saleEnd);
        $this->assertSame($options['description'], $tokenOptions->description);
        $this->assertSame($options['url'], $tokenOptions->url);
        $this->assertSame($options['freeBandwidth'], $tokenOptions->freeBandwidth);
        $this->assertSame($options['freeBandwidthLimit'], $tokenOptions->freeBandwidthLimit);
        $this->assertSame($options['frozenAmount'], $tokenOptions->frozenAmount);
        $this->assertSame($options['frozenDuration'], $tokenOptions->frozenDuration);
    }

    public function testConstructorWithMinimalData(): void
    {
        $tokenOptions = new TokenOptions(
            name: 'Minimal Token',
            abbreviation: 'MIN',
            totalSupply: 1,
            trxRatio: 1,
            saleStart: time() + 100,
            saleEnd: time() + 200,
            description: 'Minimal description',
            url: 'https://example.com',
        );

        $this->assertSame(0, $tokenOptions->freeBandwidth);
        $this->assertSame(0, $tokenOptions->freeBandwidthLimit);
        $this->assertSame(0, $tokenOptions->frozenAmount);
        $this->assertSame(0, $tokenOptions->frozenDuration);
    }

    public function testFromArrayWithValidData(): void
    {
        $options = $this->getValidOptions();
        $startTimeStamp = time();

        $tokenOptions = TokenOptions::fromArray($options, $startTimeStamp);

        $this->assertSame($options['name'], $tokenOptions->name);
        $this->assertSame($options['abbreviation'], $tokenOptions->abbreviation);
        $this->assertSame($options['totalSupply'], $tokenOptions->totalSupply);
        $this->assertSame($options['trxRatio'], $tokenOptions->trxRatio);
        $this->assertSame($options['saleStart'], $tokenOptions->saleStart);
        $this->assertSame($options['saleEnd'], $tokenOptions->saleEnd);
        $this->assertSame($options['description'], $tokenOptions->description);
        $this->assertSame($options['url'], $tokenOptions->url);
    }

    public function testFromArrayWithMissingOptionalFields(): void
    {
        $options = [
            'name' => 'Test Token',
            'abbreviation' => 'TTK',
            'totalSupply' => 1000000,
            'trxRatio' => 10,
            'saleStart' => time() + 3600,
            'saleEnd' => time() + 7200,
            'description' => 'Test description',
            'url' => 'https://example.com',
        ];
        $startTimeStamp = time();

        $tokenOptions = TokenOptions::fromArray($options, $startTimeStamp);

        $this->assertSame(0, $tokenOptions->freeBandwidth);
        $this->assertSame(0, $tokenOptions->freeBandwidthLimit);
        $this->assertSame(0, $tokenOptions->frozenAmount);
        $this->assertSame(0, $tokenOptions->frozenDuration);
    }

    public function testFromArrayThrowsExceptionWhenSaleStartNotAfterTimestamp(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid sale start timestamp provided');

        $options = $this->getValidOptions();
        $startTimeStamp = time() + 10000; // Future timestamp
        $options['saleStart'] = $startTimeStamp; // Equal to startTimeStamp
        $options['saleEnd'] = $startTimeStamp + 1000; // Valid saleEnd

        TokenOptions::fromArray($options, $startTimeStamp);
    }

    public function testFromArrayThrowsExceptionWhenSaleStartBeforeTimestamp(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid sale start timestamp provided');

        $options = $this->getValidOptions();
        $startTimeStamp = time() + 10000;
        $options['saleStart'] = $startTimeStamp - 1; // Before startTimeStamp
        $options['saleEnd'] = $startTimeStamp + 1000; // Valid saleEnd

        TokenOptions::fromArray($options, $startTimeStamp);
    }

    public function testToArray(): void
    {
        $options = $this->getValidOptions();

        $tokenOptions = new TokenOptions(
            name: $options['name'],
            abbreviation: $options['abbreviation'],
            totalSupply: $options['totalSupply'],
            trxRatio: $options['trxRatio'],
            saleStart: $options['saleStart'],
            saleEnd: $options['saleEnd'],
            description: $options['description'],
            url: $options['url'],
            freeBandwidth: $options['freeBandwidth'],
            freeBandwidthLimit: $options['freeBandwidthLimit'],
            frozenAmount: $options['frozenAmount'],
            frozenDuration: $options['frozenDuration'],
        );

        $result = $tokenOptions->toArray();

        $this->assertSame($options, $result);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('abbreviation', $result);
        $this->assertArrayHasKey('totalSupply', $result);
        $this->assertArrayHasKey('trxRatio', $result);
        $this->assertArrayHasKey('saleStart', $result);
        $this->assertArrayHasKey('saleEnd', $result);
        $this->assertArrayHasKey('description', $result);
        $this->assertArrayHasKey('url', $result);
        $this->assertArrayHasKey('freeBandwidth', $result);
        $this->assertArrayHasKey('freeBandwidthLimit', $result);
        $this->assertArrayHasKey('frozenAmount', $result);
        $this->assertArrayHasKey('frozenDuration', $result);
    }

    public function testValidateNameThrowsExceptionForEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid token name provided');

        $options = $this->getValidOptions();
        $options['name'] = '';

        new TokenOptions(
            name: $options['name'],
            abbreviation: $options['abbreviation'],
            totalSupply: $options['totalSupply'],
            trxRatio: $options['trxRatio'],
            saleStart: $options['saleStart'],
            saleEnd: $options['saleEnd'],
            description: $options['description'],
            url: $options['url'],
        );
    }

    public function testValidateAbbreviationThrowsExceptionForEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid token abbreviation provided');

        $options = $this->getValidOptions();
        $options['abbreviation'] = '';

        new TokenOptions(
            name: $options['name'],
            abbreviation: $options['abbreviation'],
            totalSupply: $options['totalSupply'],
            trxRatio: $options['trxRatio'],
            saleStart: $options['saleStart'],
            saleEnd: $options['saleEnd'],
            description: $options['description'],
            url: $options['url'],
        );
    }

    public function testValidateSupplyThrowsExceptionForZero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid supply amount provided');

        $options = $this->getValidOptions();
        $options['totalSupply'] = 0;

        new TokenOptions(
            name: $options['name'],
            abbreviation: $options['abbreviation'],
            totalSupply: $options['totalSupply'],
            trxRatio: $options['trxRatio'],
            saleStart: $options['saleStart'],
            saleEnd: $options['saleEnd'],
            description: $options['description'],
            url: $options['url'],
        );
    }

    public function testValidateSupplyThrowsExceptionForNegative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid supply amount provided');

        $options = $this->getValidOptions();
        $options['totalSupply'] = -1;

        new TokenOptions(
            name: $options['name'],
            abbreviation: $options['abbreviation'],
            totalSupply: $options['totalSupply'],
            trxRatio: $options['trxRatio'],
            saleStart: $options['saleStart'],
            saleEnd: $options['saleEnd'],
            description: $options['description'],
            url: $options['url'],
        );
    }

    public function testValidateRatioThrowsExceptionForZero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('TRX ratio must be a positive integer');

        $options = $this->getValidOptions();
        $options['trxRatio'] = 0;

        new TokenOptions(
            name: $options['name'],
            abbreviation: $options['abbreviation'],
            totalSupply: $options['totalSupply'],
            trxRatio: $options['trxRatio'],
            saleStart: $options['saleStart'],
            saleEnd: $options['saleEnd'],
            description: $options['description'],
            url: $options['url'],
        );
    }

    public function testValidateRatioThrowsExceptionForNegative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('TRX ratio must be a positive integer');

        $options = $this->getValidOptions();
        $options['trxRatio'] = -1;

        new TokenOptions(
            name: $options['name'],
            abbreviation: $options['abbreviation'],
            totalSupply: $options['totalSupply'],
            trxRatio: $options['trxRatio'],
            saleStart: $options['saleStart'],
            saleEnd: $options['saleEnd'],
            description: $options['description'],
            url: $options['url'],
        );
    }

    public function testValidateSaleTimeWindowThrowsExceptionForZeroSaleStart(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid sale start timestamp provided');

        $options = $this->getValidOptions();
        $options['saleStart'] = 0;

        new TokenOptions(
            name: $options['name'],
            abbreviation: $options['abbreviation'],
            totalSupply: $options['totalSupply'],
            trxRatio: $options['trxRatio'],
            saleStart: $options['saleStart'],
            saleEnd: $options['saleEnd'],
            description: $options['description'],
            url: $options['url'],
        );
    }

    public function testValidateSaleTimeWindowThrowsExceptionForNegativeSaleStart(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid sale start timestamp provided');

        $options = $this->getValidOptions();
        $options['saleStart'] = -1;

        new TokenOptions(
            name: $options['name'],
            abbreviation: $options['abbreviation'],
            totalSupply: $options['totalSupply'],
            trxRatio: $options['trxRatio'],
            saleStart: $options['saleStart'],
            saleEnd: $options['saleEnd'],
            description: $options['description'],
            url: $options['url'],
        );
    }

    public function testValidateSaleTimeWindowThrowsExceptionForSaleEndEqualToSaleStart(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid sale end timestamp provided');

        $options = $this->getValidOptions();
        $options['saleStart'] = time() + 1000;
        $options['saleEnd'] = $options['saleStart'];

        new TokenOptions(
            name: $options['name'],
            abbreviation: $options['abbreviation'],
            totalSupply: $options['totalSupply'],
            trxRatio: $options['trxRatio'],
            saleStart: $options['saleStart'],
            saleEnd: $options['saleEnd'],
            description: $options['description'],
            url: $options['url'],
        );
    }

    public function testValidateSaleTimeWindowThrowsExceptionForSaleEndBeforeSaleStart(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid sale end timestamp provided');

        $options = $this->getValidOptions();
        $options['saleStart'] = time() + 1000;
        $options['saleEnd'] = $options['saleStart'] - 1;

        new TokenOptions(
            name: $options['name'],
            abbreviation: $options['abbreviation'],
            totalSupply: $options['totalSupply'],
            trxRatio: $options['trxRatio'],
            saleStart: $options['saleStart'],
            saleEnd: $options['saleEnd'],
            description: $options['description'],
            url: $options['url'],
        );
    }

    public function testValidateDescriptionThrowsExceptionForEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid token description provided');

        $options = $this->getValidOptions();
        $options['description'] = '';

        new TokenOptions(
            name: $options['name'],
            abbreviation: $options['abbreviation'],
            totalSupply: $options['totalSupply'],
            trxRatio: $options['trxRatio'],
            saleStart: $options['saleStart'],
            saleEnd: $options['saleEnd'],
            description: $options['description'],
            url: $options['url'],
        );
    }

    public function testValidateUrlThrowsExceptionForInvalidUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid token url provided');

        $options = $this->getValidOptions();
        $options['url'] = 'not-a-valid-url';

        new TokenOptions(
            name: $options['name'],
            abbreviation: $options['abbreviation'],
            totalSupply: $options['totalSupply'],
            trxRatio: $options['trxRatio'],
            saleStart: $options['saleStart'],
            saleEnd: $options['saleEnd'],
            description: $options['description'],
            url: $options['url'],
        );
    }

    public function testValidateUrlThrowsExceptionForEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid token url provided');

        $options = $this->getValidOptions();
        $options['url'] = '';

        new TokenOptions(
            name: $options['name'],
            abbreviation: $options['abbreviation'],
            totalSupply: $options['totalSupply'],
            trxRatio: $options['trxRatio'],
            saleStart: $options['saleStart'],
            saleEnd: $options['saleEnd'],
            description: $options['description'],
            url: $options['url'],
        );
    }

    public function testValidateUrlAcceptsHttpsUrl(): void
    {
        $options = $this->getValidOptions();
        $options['url'] = 'https://example.com/token';

        $tokenOptions = new TokenOptions(
            name: $options['name'],
            abbreviation: $options['abbreviation'],
            totalSupply: $options['totalSupply'],
            trxRatio: $options['trxRatio'],
            saleStart: $options['saleStart'],
            saleEnd: $options['saleEnd'],
            description: $options['description'],
            url: $options['url'],
        );

        $this->assertSame($options['url'], $tokenOptions->url);
    }

    public function testValidateUrlAcceptsHttpUrl(): void
    {
        $options = $this->getValidOptions();
        $options['url'] = 'http://example.com/token';

        $tokenOptions = new TokenOptions(
            name: $options['name'],
            abbreviation: $options['abbreviation'],
            totalSupply: $options['totalSupply'],
            trxRatio: $options['trxRatio'],
            saleStart: $options['saleStart'],
            saleEnd: $options['saleEnd'],
            description: $options['description'],
            url: $options['url'],
        );

        $this->assertSame($options['url'], $tokenOptions->url);
    }

    public function testValidateBandwidthThrowsExceptionForNegativeFreeBandwidth(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid free bandwidth amount provided');

        $options = $this->getValidOptions();

        new TokenOptions(
            name: $options['name'],
            abbreviation: $options['abbreviation'],
            totalSupply: $options['totalSupply'],
            trxRatio: $options['trxRatio'],
            saleStart: $options['saleStart'],
            saleEnd: $options['saleEnd'],
            description: $options['description'],
            url: $options['url'],
            freeBandwidth: -1,
        );
    }

    public function testValidateBandwidthThrowsExceptionForNegativeFreeBandwidthLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid free bandwidth limit provided');

        $options = $this->getValidOptions();

        new TokenOptions(
            name: $options['name'],
            abbreviation: $options['abbreviation'],
            totalSupply: $options['totalSupply'],
            trxRatio: $options['trxRatio'],
            saleStart: $options['saleStart'],
            saleEnd: $options['saleEnd'],
            description: $options['description'],
            url: $options['url'],
            freeBandwidth: 0,
            freeBandwidthLimit: -1,
        );
    }

    public function testValidateBandwidthThrowsExceptionWhenFreeBandwidthSetButLimitIsZero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid free bandwidth limit provided');

        $options = $this->getValidOptions();

        new TokenOptions(
            name: $options['name'],
            abbreviation: $options['abbreviation'],
            totalSupply: $options['totalSupply'],
            trxRatio: $options['trxRatio'],
            saleStart: $options['saleStart'],
            saleEnd: $options['saleEnd'],
            description: $options['description'],
            url: $options['url'],
            freeBandwidth: 100,
            freeBandwidthLimit: 0,
        );
    }

    public function testValidateBandwidthAcceptsZeroForBoth(): void
    {
        $options = $this->getValidOptions();

        $tokenOptions = new TokenOptions(
            name: $options['name'],
            abbreviation: $options['abbreviation'],
            totalSupply: $options['totalSupply'],
            trxRatio: $options['trxRatio'],
            saleStart: $options['saleStart'],
            saleEnd: $options['saleEnd'],
            description: $options['description'],
            url: $options['url'],
            freeBandwidth: 0,
            freeBandwidthLimit: 0,
        );

        $this->assertSame(0, $tokenOptions->freeBandwidth);
        $this->assertSame(0, $tokenOptions->freeBandwidthLimit);
    }

    public function testValidateBandwidthAcceptsValidValues(): void
    {
        $options = $this->getValidOptions();

        $tokenOptions = new TokenOptions(
            name: $options['name'],
            abbreviation: $options['abbreviation'],
            totalSupply: $options['totalSupply'],
            trxRatio: $options['trxRatio'],
            saleStart: $options['saleStart'],
            saleEnd: $options['saleEnd'],
            description: $options['description'],
            url: $options['url'],
            freeBandwidth: 1000,
            freeBandwidthLimit: 5000,
        );

        $this->assertSame(1000, $tokenOptions->freeBandwidth);
        $this->assertSame(5000, $tokenOptions->freeBandwidthLimit);
    }

    public function testValidateFrozenSettingsThrowsExceptionForNegativeFrozenAmount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid frozen supply provided');

        $options = $this->getValidOptions();

        new TokenOptions(
            name: $options['name'],
            abbreviation: $options['abbreviation'],
            totalSupply: $options['totalSupply'],
            trxRatio: $options['trxRatio'],
            saleStart: $options['saleStart'],
            saleEnd: $options['saleEnd'],
            description: $options['description'],
            url: $options['url'],
            frozenAmount: -1,
        );
    }

    public function testValidateFrozenSettingsThrowsExceptionWhenAmountSetButDurationIsZero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid frozen supply provided');

        $options = $this->getValidOptions();

        new TokenOptions(
            name: $options['name'],
            abbreviation: $options['abbreviation'],
            totalSupply: $options['totalSupply'],
            trxRatio: $options['trxRatio'],
            saleStart: $options['saleStart'],
            saleEnd: $options['saleEnd'],
            description: $options['description'],
            url: $options['url'],
            frozenAmount: 100,
            frozenDuration: 0,
        );
    }

    public function testValidateFrozenSettingsAcceptsZeroForBoth(): void
    {
        $options = $this->getValidOptions();

        $tokenOptions = new TokenOptions(
            name: $options['name'],
            abbreviation: $options['abbreviation'],
            totalSupply: $options['totalSupply'],
            trxRatio: $options['trxRatio'],
            saleStart: $options['saleStart'],
            saleEnd: $options['saleEnd'],
            description: $options['description'],
            url: $options['url'],
            frozenAmount: 0,
            frozenDuration: 0,
        );

        $this->assertSame(0, $tokenOptions->frozenAmount);
        $this->assertSame(0, $tokenOptions->frozenDuration);
    }

    public function testValidateFrozenSettingsAcceptsValidValues(): void
    {
        $options = $this->getValidOptions();

        $tokenOptions = new TokenOptions(
            name: $options['name'],
            abbreviation: $options['abbreviation'],
            totalSupply: $options['totalSupply'],
            trxRatio: $options['trxRatio'],
            saleStart: $options['saleStart'],
            saleEnd: $options['saleEnd'],
            description: $options['description'],
            url: $options['url'],
            frozenAmount: 100,
            frozenDuration: 3,
        );

        $this->assertSame(100, $tokenOptions->frozenAmount);
        $this->assertSame(3, $tokenOptions->frozenDuration);
    }

    public function testReadonlyPropertiesCannotBeModified(): void
    {
        $options = $this->getValidOptions();

        $tokenOptions = new TokenOptions(
            name: $options['name'],
            abbreviation: $options['abbreviation'],
            totalSupply: $options['totalSupply'],
            trxRatio: $options['trxRatio'],
            saleStart: $options['saleStart'],
            saleEnd: $options['saleEnd'],
            description: $options['description'],
            url: $options['url'],
        );

        // 使用反射验证 readonly 属性在运行时的行为
        $property = new \ReflectionProperty(TokenOptions::class, 'name');
        $this->assertTrue($property->isReadOnly(), 'Property name should be readonly');

        // 尝试通过反射修改 readonly 属性会抛出错误
        $this->expectException(\Error::class);
        $this->expectExceptionMessageMatches('/Cannot modify readonly property/');
        $property->setValue($tokenOptions, 'Modified Name');
    }

    public function testBoundaryValueForMinimumValidSupply(): void
    {
        $options = $this->getValidOptions();
        $options['totalSupply'] = 1;

        $tokenOptions = new TokenOptions(
            name: $options['name'],
            abbreviation: $options['abbreviation'],
            totalSupply: $options['totalSupply'],
            trxRatio: $options['trxRatio'],
            saleStart: $options['saleStart'],
            saleEnd: $options['saleEnd'],
            description: $options['description'],
            url: $options['url'],
        );

        $this->assertSame(1, $tokenOptions->totalSupply);
    }

    public function testBoundaryValueForMinimumValidRatio(): void
    {
        $options = $this->getValidOptions();
        $options['trxRatio'] = 1;

        $tokenOptions = new TokenOptions(
            name: $options['name'],
            abbreviation: $options['abbreviation'],
            totalSupply: $options['totalSupply'],
            trxRatio: $options['trxRatio'],
            saleStart: $options['saleStart'],
            saleEnd: $options['saleEnd'],
            description: $options['description'],
            url: $options['url'],
        );

        $this->assertSame(1, $tokenOptions->trxRatio);
    }

    public function testBoundaryValueForMinimumValidSaleTimeWindow(): void
    {
        $options = $this->getValidOptions();
        $options['saleStart'] = 1;
        $options['saleEnd'] = 2;

        $tokenOptions = new TokenOptions(
            name: $options['name'],
            abbreviation: $options['abbreviation'],
            totalSupply: $options['totalSupply'],
            trxRatio: $options['trxRatio'],
            saleStart: $options['saleStart'],
            saleEnd: $options['saleEnd'],
            description: $options['description'],
            url: $options['url'],
        );

        $this->assertSame(1, $tokenOptions->saleStart);
        $this->assertSame(2, $tokenOptions->saleEnd);
    }

    public function testFromArrayHandlesStringToIntConversion(): void
    {
        $options = [
            'name' => 'Test Token',
            'abbreviation' => 'TTK',
            'totalSupply' => '1000000',
            'trxRatio' => '10',
            'saleStart' => (string) (time() + 3600),
            'saleEnd' => (string) (time() + 7200),
            'description' => 'Test description',
            'url' => 'https://example.com',
        ];
        $startTimeStamp = time();

        $tokenOptions = TokenOptions::fromArray($options, $startTimeStamp);

        $this->assertIsInt($tokenOptions->totalSupply);
        $this->assertIsInt($tokenOptions->trxRatio);
        $this->assertIsInt($tokenOptions->saleStart);
        $this->assertIsInt($tokenOptions->saleEnd);
    }

    public function testCompleteWorkflowFromArrayToArrayRoundtrip(): void
    {
        $originalOptions = $this->getValidOptions();
        $startTimeStamp = time();

        $tokenOptions = TokenOptions::fromArray($originalOptions, $startTimeStamp);
        $resultArray = $tokenOptions->toArray();

        $this->assertSame($originalOptions, $resultArray);
    }
}
