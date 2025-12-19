<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\TronAPI\Exception\InvalidArgumentException;
use Tourze\TronAPI\Provider\InMemoryHttpProvider;
use Tourze\TronAPI\Service\TokenService;
use Tourze\TronAPI\Tron;
use Tourze\TronAPI\TronManager;

/**
 * @internal
 */
#[CoversClass(TokenService::class)]
class TokenServiceTest extends TestCase
{
    private InMemoryHttpProvider $fullNodeProvider;

    private Tron $tron;

    private TokenService $service;

    protected function setUp(): void
    {
        $this->fullNodeProvider = new InMemoryHttpProvider();

        $manager = new TronManager([
            'fullNode' => $this->fullNodeProvider,
            'solidityNode' => new InMemoryHttpProvider(),
            'eventServer' => new InMemoryHttpProvider(),
            'explorer' => new InMemoryHttpProvider(),
        ]);

        $this->tron = new Tron();
        // 使用反射设置 manager
        $reflection = new \ReflectionProperty(Tron::class, 'manager');
        $reflection->setValue($this->tron, $manager);

        $this->service = new TokenService($this->tron);
    }

    public function testCanBeInstantiated(): void
    {
        $tron = new Tron();
        $service = new TokenService($tron);
        $this->assertInstanceOf(TokenService::class, $service);
    }

    // ===================== purchaseToken 测试 =====================

    public function testPurchaseTokenThrowsExceptionForZeroAmount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid amount provided');

        $this->service->purchaseToken('TIssuerAddress', 'token123', 0, 'TBuyerAddress');
    }

    public function testPurchaseTokenThrowsExceptionForNegativeAmount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid amount provided');

        $this->service->purchaseToken('TIssuerAddress', 'token123', -100, 'TBuyerAddress');
    }

    public function testPurchaseTokenThrowsExceptionWhenResponseContainsError(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Insufficient balance');

        $this->fullNodeProvider->setResponse('wallet/participateassetissue', [
            'Error' => 'Insufficient balance',
        ]);

        $this->service->purchaseToken('TIssuerAddress', 'token123', 1000, 'TBuyerAddress');
    }

    public function testPurchaseTokenReturnsSuccessResponse(): void
    {
        $expectedResponse = [
            'txID' => 'abc123',
            'result' => true,
        ];

        $this->fullNodeProvider->setResponse('wallet/participateassetissue', $expectedResponse);

        $result = $this->service->purchaseToken('TIssuerAddress', 'token123', 1000, 'TBuyerAddress');

        $this->assertSame($expectedResponse, $result);

        // 验证请求参数
        $lastRequest = $this->fullNodeProvider->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertSame('wallet/participateassetissue', $lastRequest['url']);
        $this->assertArrayHasKey('to_address', $lastRequest['payload']);
        $this->assertArrayHasKey('owner_address', $lastRequest['payload']);
        $this->assertArrayHasKey('asset_name', $lastRequest['payload']);
        $this->assertArrayHasKey('amount', $lastRequest['payload']);
    }

    // ===================== createToken 测试 =====================

    public function testCreateTokenThrowsExceptionForInvalidName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid token name provided');

        $futureTimestamp = (time() + 3600) * 1000;
        $options = [
            'name' => '', // Invalid: empty name
            'abbreviation' => 'TEST',
            'totalSupply' => 1000000,
            'description' => 'Test token',
            'url' => 'https://example.com',
            'saleStart' => $futureTimestamp,
            'saleEnd' => $futureTimestamp + 86400000,
        ];

        $this->service->createToken($options);
    }

    public function testCreateTokenThrowsExceptionForInvalidAbbreviation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid token abbreviation provided');

        $futureTimestamp = (time() + 3600) * 1000;
        $options = [
            'name' => 'TestToken',
            'abbreviation' => '', // Invalid: empty abbreviation
            'totalSupply' => 1000000,
            'description' => 'Test token',
            'url' => 'https://example.com',
            'saleStart' => $futureTimestamp,
            'saleEnd' => $futureTimestamp + 86400000,
        ];

        $this->service->createToken($options);
    }

    public function testCreateTokenThrowsExceptionForInvalidSupply(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid supply amount provided');

        $futureTimestamp = (time() + 3600) * 1000;
        $options = [
            'name' => 'TestToken',
            'abbreviation' => 'TEST',
            'totalSupply' => 0, // Invalid: zero supply
            'description' => 'Test token',
            'url' => 'https://example.com',
            'saleStart' => $futureTimestamp,
            'saleEnd' => $futureTimestamp + 86400000,
        ];

        $this->service->createToken($options);
    }

    public function testCreateTokenThrowsExceptionForInvalidUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid token url provided');

        $futureTimestamp = (time() + 3600) * 1000;
        $options = [
            'name' => 'TestToken',
            'abbreviation' => 'TEST',
            'totalSupply' => 1000000,
            'description' => 'Test token',
            'url' => 'not-a-valid-url', // Invalid URL
            'saleStart' => $futureTimestamp,
            'saleEnd' => $futureTimestamp + 86400000,
        ];

        $this->service->createToken($options);
    }

    public function testCreateTokenThrowsExceptionWhenSaleEndBeforeSaleStart(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid sale end timestamp provided');

        $futureTimestamp = (time() + 3600) * 1000;
        $options = [
            'name' => 'TestToken',
            'abbreviation' => 'TEST',
            'totalSupply' => 1000000,
            'description' => 'Test token',
            'url' => 'https://example.com',
            'saleStart' => $futureTimestamp + 86400000,
            'saleEnd' => $futureTimestamp, // Invalid: end before start
        ];

        $this->service->createToken($options);
    }

    public function testCreateTokenThrowsExceptionWhenSaleStartIsInPast(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid sale start timestamp provided');

        $pastTimestamp = (time() - 3600) * 1000;
        $options = [
            'name' => 'TestToken',
            'abbreviation' => 'TEST',
            'totalSupply' => 1000000,
            'description' => 'Test token',
            'url' => 'https://example.com',
            'saleStart' => $pastTimestamp, // Invalid: in the past
            'saleEnd' => $pastTimestamp + 86400000,
        ];

        $this->service->createToken($options);
    }

    public function testCreateTokenUsesDefaultIssuerAddress(): void
    {
        $this->tron->address = ['hex' => '41defaultaddress'];

        $this->fullNodeProvider->setResponse('wallet/createassetissue', ['txID' => 'abc123']);

        $futureTimestamp = (time() + 3600) * 1000;
        $options = [
            'name' => 'TestToken',
            'abbreviation' => 'TEST',
            'totalSupply' => 1000000,
            'description' => 'Test token',
            'url' => 'https://example.com',
            'saleStart' => $futureTimestamp,
            'saleEnd' => $futureTimestamp + 86400000,
        ];

        $this->service->createToken($options);

        // 验证请求参数
        $lastRequest = $this->fullNodeProvider->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertSame('wallet/createassetissue', $lastRequest['url']);
        $this->assertArrayHasKey('owner_address', $lastRequest['payload']);
        // 验证使用了默认地址（通过 address2HexString 转换）
        $expectedAddress = $this->tron->address2HexString($this->tron->address['hex']);
        $this->assertSame($expectedAddress, $lastRequest['payload']['owner_address']);
    }

    // ===================== updateToken 测试 =====================

    public function testUpdateTokenThrowsExceptionWhenAddressIsNull(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Owner Address not specified');

        $this->service->updateToken('New description', 'https://example.com', 0, 0, null);
    }

    public function testUpdateTokenThrowsExceptionForNegativeFreeBandwidth(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid free bandwidth amount provided');

        $this->service->updateToken('New description', 'https://example.com', -100, 0, 'TOwnerAddress');
    }

    public function testUpdateTokenThrowsExceptionForNegativeFreeBandwidthLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid free bandwidth limit provided');

        $this->service->updateToken('New description', 'https://example.com', 0, -100, 'TOwnerAddress');
    }

    public function testUpdateTokenReturnsSuccessResponse(): void
    {
        $expectedResponse = [
            'txID' => 'update123',
            'result' => true,
        ];

        $this->fullNodeProvider->setResponse('wallet/updateasset', $expectedResponse);

        $result = $this->service->updateToken(
            'Updated description',
            'https://updated.com',
            1000,
            5000,
            'TOwnerAddress'
        );

        $this->assertSame($expectedResponse, $result);

        // 验证请求参数
        $lastRequest = $this->fullNodeProvider->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertSame('wallet/updateasset', $lastRequest['url']);
        $this->assertArrayHasKey('owner_address', $lastRequest['payload']);
        $this->assertArrayHasKey('description', $lastRequest['payload']);
        $this->assertArrayHasKey('url', $lastRequest['payload']);
        $this->assertArrayHasKey('new_limit', $lastRequest['payload']);
        $this->assertArrayHasKey('new_public_limit', $lastRequest['payload']);
    }

    // ===================== Private Methods 测试（通过反射） =====================

    public function testNormalizeTokenOptionsAddsDefaultValues(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('normalizeTokenOptions');
        $method->setAccessible(true);

        $options = ['name' => 'TestToken'];
        $normalized = $method->invoke($this->service, $options);

        $this->assertArrayHasKey('totalSupply', $normalized);
        $this->assertArrayHasKey('trxRatio', $normalized);
        $this->assertArrayHasKey('tokenRatio', $normalized);
        $this->assertArrayHasKey('freeBandwidth', $normalized);
        $this->assertArrayHasKey('freeBandwidthLimit', $normalized);
        $this->assertArrayHasKey('frozenAmount', $normalized);
        $this->assertArrayHasKey('frozenDuration', $normalized);

        $this->assertEquals(0, $normalized['totalSupply']);
        $this->assertEquals(1, $normalized['trxRatio']);
        $this->assertEquals(1, $normalized['tokenRatio']);
        $this->assertEquals(0, $normalized['freeBandwidth']);
        $this->assertEquals(0, $normalized['freeBandwidthLimit']);
        $this->assertEquals(0, $normalized['frozenAmount']);
        $this->assertEquals(0, $normalized['frozenDuration']);
    }

    public function testNormalizeTokenOptionsPreservesProvidedValues(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('normalizeTokenOptions');
        $method->setAccessible(true);

        $options = [
            'name' => 'TestToken',
            'totalSupply' => 1000000,
            'trxRatio' => 5,
            'freeBandwidth' => 100,
        ];

        $normalized = $method->invoke($this->service, $options);

        $this->assertEquals(1000000, $normalized['totalSupply']);
        $this->assertEquals(5, $normalized['trxRatio']);
        $this->assertEquals(100, $normalized['freeBandwidth']);
        // Still has defaults for unspecified values
        $this->assertEquals(1, $normalized['tokenRatio']);
        $this->assertEquals(0, $normalized['frozenAmount']);
    }

    public function testBuildTokenDataCreatesCorrectStructure(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('buildTokenData');
        $method->setAccessible(true);

        $options = [
            'name' => 'TestToken',
            'abbreviation' => 'TEST',
            'description' => 'Test description',
            'url' => 'https://example.com',
            'totalSupply' => 1000000,
            'trxRatio' => 1,
            'tokenRatio' => 1,
            'saleStart' => 1000000,
            'saleEnd' => 2000000,
            'freeBandwidth' => 0,
            'freeBandwidthLimit' => 0,
            'frozenAmount' => 0,
            'frozenDuration' => 0,
        ];

        $result = $method->invoke($this->service, $options, 'TIssuerAddress');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('owner_address', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('abbr', $result);
        $this->assertArrayHasKey('description', $result);
        $this->assertArrayHasKey('url', $result);
        $this->assertArrayHasKey('total_supply', $result);
        $this->assertArrayHasKey('trx_num', $result);
        $this->assertArrayHasKey('num', $result);
        $this->assertArrayHasKey('start_time', $result);
        $this->assertArrayHasKey('end_time', $result);
        $this->assertArrayHasKey('frozen_supply', $result);

        $this->assertEquals(1000000, $result['total_supply']);
        $this->assertIsArray($result['frozen_supply']);
        $this->assertArrayHasKey('frozen_amount', $result['frozen_supply']);
        $this->assertArrayHasKey('frozen_days', $result['frozen_supply']);
    }

    public function testBuildTokenDataIncludesPrecisionWhenProvided(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('buildTokenData');
        $method->setAccessible(true);

        $options = [
            'name' => 'TestToken',
            'abbreviation' => 'TEST',
            'description' => 'Test description',
            'url' => 'https://example.com',
            'totalSupply' => 1000000,
            'trxRatio' => 1,
            'tokenRatio' => 1,
            'saleStart' => 1000000,
            'saleEnd' => 2000000,
            'freeBandwidth' => 0,
            'freeBandwidthLimit' => 0,
            'frozenAmount' => 0,
            'frozenDuration' => 0,
            'precision' => 6,
        ];

        $result = $method->invoke($this->service, $options, 'TIssuerAddress');

        $this->assertArrayHasKey('precision', $result);
        $this->assertEquals(6, $result['precision']);
    }

    public function testBuildTokenDataIncludesVoteScoreWhenProvided(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('buildTokenData');
        $method->setAccessible(true);

        $options = [
            'name' => 'TestToken',
            'abbreviation' => 'TEST',
            'description' => 'Test description',
            'url' => 'https://example.com',
            'totalSupply' => 1000000,
            'trxRatio' => 1,
            'tokenRatio' => 1,
            'saleStart' => 1000000,
            'saleEnd' => 2000000,
            'freeBandwidth' => 0,
            'freeBandwidthLimit' => 0,
            'frozenAmount' => 0,
            'frozenDuration' => 0,
            'voteScore' => 100,
        ];

        $result = $method->invoke($this->service, $options, 'TIssuerAddress');

        $this->assertArrayHasKey('vote_score', $result);
        $this->assertEquals(100, $result['vote_score']);
    }
}
