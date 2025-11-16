<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\TronAPI\Exception\InvalidArgumentException;
use Tourze\TronAPI\Service\TokenService;
use Tourze\TronAPI\Tron;
use Tourze\TronAPI\TronManager;

/**
 * @internal
 */
#[CoversClass(TokenService::class)]
class TokenServiceTest extends TestCase
{
    private function createMockTron(): Tron
    {
        return $this->createMock(Tron::class);
    }

    private function createMockManager(): TronManager
    {
        return $this->createMock(TronManager::class);
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

        $mockTron = $this->createMockTron();
        $service = new TokenService($mockTron);

        $service->purchaseToken('TIssuerAddress', 'token123', 0, 'TBuyerAddress');
    }

    public function testPurchaseTokenThrowsExceptionForNegativeAmount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid amount provided');

        $mockTron = $this->createMockTron();
        $service = new TokenService($mockTron);

        $service->purchaseToken('TIssuerAddress', 'token123', -100, 'TBuyerAddress');
    }

    public function testPurchaseTokenThrowsExceptionWhenResponseContainsError(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Insufficient balance');

        $mockManager = $this->createMockManager();
        $mockTron = $this->createMockTron();

        $mockTron->expects($this->once())
            ->method('getManager')
            ->willReturn($mockManager)
        ;

        $mockTron->method('address2HexString')
            ->willReturnCallback(fn ($addr) => '41' . strtolower($addr))
        ;

        $mockTron->expects($this->once())
            ->method('stringUtf8toHex')
            ->with('token123')
            ->willReturn('746f6b656e313233')
        ;

        $mockManager->expects($this->once())
            ->method('request')
            ->willReturn(['Error' => 'Insufficient balance'])
        ;

        $service = new TokenService($mockTron);
        $service->purchaseToken('TIssuerAddress', 'token123', 1000, 'TBuyerAddress');
    }

    public function testPurchaseTokenReturnsSuccessResponse(): void
    {
        $mockManager = $this->createMockManager();
        $mockTron = $this->createMockTron();

        $mockTron->expects($this->once())
            ->method('getManager')
            ->willReturn($mockManager)
        ;

        $mockTron->method('address2HexString')
            ->willReturnCallback(fn ($addr) => '41' . $addr)
        ;

        $mockTron->method('stringUtf8toHex')
            ->willReturn('746f6b656e313233')
        ;

        $mockTron->method('toTron')
            ->willReturn(1000000)
        ;

        $expectedResponse = [
            'txID' => 'abc123',
            'result' => true,
        ];

        $mockManager->expects($this->once())
            ->method('request')
            ->with(
                'wallet/participateassetissue',
                self::callback(function ($params) {
                    return isset($params['to_address'])
                        && isset($params['owner_address'], $params['asset_name'], $params['amount']);
                })
            )
            ->willReturn($expectedResponse)
        ;

        $service = new TokenService($mockTron);
        $result = $service->purchaseToken('TIssuerAddress', 'token123', 1000, 'TBuyerAddress');

        $this->assertSame($expectedResponse, $result);
    }

    // ===================== createToken 测试 =====================

    public function testCreateTokenThrowsExceptionForInvalidName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid token name provided');

        $mockTron = $this->createMockTron();
        $service = new TokenService($mockTron);

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

        $service->createToken($options);
    }

    public function testCreateTokenThrowsExceptionForInvalidAbbreviation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid token abbreviation provided');

        $mockTron = $this->createMockTron();
        $service = new TokenService($mockTron);

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

        $service->createToken($options);
    }

    public function testCreateTokenThrowsExceptionForInvalidSupply(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid supply amount provided');

        $mockTron = $this->createMockTron();
        $service = new TokenService($mockTron);

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

        $service->createToken($options);
    }

    public function testCreateTokenThrowsExceptionForInvalidUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid token url provided');

        $mockTron = $this->createMockTron();
        $service = new TokenService($mockTron);

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

        $service->createToken($options);
    }

    public function testCreateTokenThrowsExceptionWhenSaleEndBeforeSaleStart(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid sale end timestamp provided');

        $mockTron = $this->createMockTron();
        $service = new TokenService($mockTron);

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

        $service->createToken($options);
    }

    public function testCreateTokenThrowsExceptionWhenSaleStartIsInPast(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid sale start timestamp provided');

        $mockTron = $this->createMockTron();
        $service = new TokenService($mockTron);

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

        $service->createToken($options);
    }

    public function testCreateTokenUsesDefaultIssuerAddress(): void
    {
        $mockManager = $this->createMockManager();
        $mockTron = $this->createMockTron();

        $mockTron->address = ['hex' => '41defaultaddress'];

        $mockTron->expects($this->once())
            ->method('getManager')
            ->willReturn($mockManager)
        ;

        $mockTron->method('address2HexString')
            ->willReturn('41defaultaddress')
        ;

        $mockTron->method('stringUtf8toHex')
            ->willReturnCallback(fn ($str) => bin2hex($str))
        ;

        $mockManager->expects($this->once())
            ->method('request')
            ->with('wallet/createassetissue', self::callback(function ($data) {
                return '41defaultaddress' === $data['owner_address'];
            }))
            ->willReturn(['txID' => 'abc123'])
        ;

        $service = new TokenService($mockTron);

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

        $service->createToken($options);
    }

    // ===================== updateToken 测试 =====================

    public function testUpdateTokenThrowsExceptionWhenAddressIsNull(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Owner Address not specified');

        $mockTron = $this->createMockTron();
        $service = new TokenService($mockTron);

        $service->updateToken('New description', 'https://example.com', 0, 0, null);
    }

    public function testUpdateTokenThrowsExceptionForNegativeFreeBandwidth(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid free bandwidth amount provided');

        $mockTron = $this->createMockTron();
        $service = new TokenService($mockTron);

        $service->updateToken('New description', 'https://example.com', -100, 0, 'TOwnerAddress');
    }

    public function testUpdateTokenThrowsExceptionForNegativeFreeBandwidthLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid free bandwidth limit provided');

        $mockTron = $this->createMockTron();
        $service = new TokenService($mockTron);

        $service->updateToken('New description', 'https://example.com', 0, -100, 'TOwnerAddress');
    }

    public function testUpdateTokenReturnsSuccessResponse(): void
    {
        $mockManager = $this->createMockManager();
        $mockTron = $this->createMockTron();

        $mockTron->expects($this->once())
            ->method('getManager')
            ->willReturn($mockManager)
        ;

        $mockTron->method('address2HexString')
            ->willReturn('41owneraddress')
        ;

        $mockTron->method('stringUtf8toHex')
            ->willReturnCallback(fn ($str) => bin2hex($str))
        ;

        $expectedResponse = [
            'txID' => 'update123',
            'result' => true,
        ];

        $mockManager->expects($this->once())
            ->method('request')
            ->with(
                'wallet/updateasset',
                self::callback(function ($params) {
                    return isset($params['owner_address'])
                        && isset($params['description'], $params['url'], $params['new_limit'], $params['new_public_limit']);
                })
            )
            ->willReturn($expectedResponse)
        ;

        $service = new TokenService($mockTron);
        $result = $service->updateToken(
            'Updated description',
            'https://updated.com',
            1000,
            5000,
            'TOwnerAddress'
        );

        $this->assertSame($expectedResponse, $result);
    }

    // ===================== Private Methods 测试（通过反射） =====================

    public function testNormalizeTokenOptionsAddsDefaultValues(): void
    {
        $mockTron = $this->createMockTron();
        $service = new TokenService($mockTron);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('normalizeTokenOptions');
        $method->setAccessible(true);

        $options = ['name' => 'TestToken'];
        $normalized = $method->invoke($service, $options);

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
        $mockTron = $this->createMockTron();
        $service = new TokenService($mockTron);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('normalizeTokenOptions');
        $method->setAccessible(true);

        $options = [
            'name' => 'TestToken',
            'totalSupply' => 1000000,
            'trxRatio' => 5,
            'freeBandwidth' => 100,
        ];

        $normalized = $method->invoke($service, $options);

        $this->assertEquals(1000000, $normalized['totalSupply']);
        $this->assertEquals(5, $normalized['trxRatio']);
        $this->assertEquals(100, $normalized['freeBandwidth']);
        // Still has defaults for unspecified values
        $this->assertEquals(1, $normalized['tokenRatio']);
        $this->assertEquals(0, $normalized['frozenAmount']);
    }

    public function testBuildTokenDataCreatesCorrectStructure(): void
    {
        $mockTron = $this->createMockTron();
        $mockTron->method('address2HexString')
            ->willReturn('41issueraddress')
        ;
        $mockTron->method('stringUtf8toHex')
            ->willReturnCallback(fn ($str) => bin2hex($str))
        ;

        $service = new TokenService($mockTron);

        $reflection = new \ReflectionClass($service);
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

        $result = $method->invoke($service, $options, 'TIssuerAddress');

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
        $mockTron = $this->createMockTron();
        $mockTron->method('address2HexString')
            ->willReturn('41issueraddress')
        ;
        $mockTron->method('stringUtf8toHex')
            ->willReturnCallback(fn ($str) => bin2hex($str))
        ;

        $service = new TokenService($mockTron);

        $reflection = new \ReflectionClass($service);
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

        $result = $method->invoke($service, $options, 'TIssuerAddress');

        $this->assertArrayHasKey('precision', $result);
        $this->assertEquals(6, $result['precision']);
    }

    public function testBuildTokenDataIncludesVoteScoreWhenProvided(): void
    {
        $mockTron = $this->createMockTron();
        $mockTron->method('address2HexString')
            ->willReturn('41issueraddress')
        ;
        $mockTron->method('stringUtf8toHex')
            ->willReturnCallback(fn ($str) => bin2hex($str))
        ;

        $service = new TokenService($mockTron);

        $reflection = new \ReflectionClass($service);
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

        $result = $method->invoke($service, $options, 'TIssuerAddress');

        $this->assertArrayHasKey('vote_score', $result);
        $this->assertEquals(100, $result['vote_score']);
    }
}
