<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Tests\Service;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\TronAPI\Exception\RuntimeException;
use Tourze\TronAPI\Service\BlockchainQueryService;
use Tourze\TronAPI\Tron;
use Tourze\TronAPI\TronManager;

/**
 * @internal
 */
#[CoversClass(BlockchainQueryService::class)]
class BlockchainQueryServiceTest extends TestCase
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
        $service = new BlockchainQueryService($tron);
        $this->assertInstanceOf(BlockchainQueryService::class, $service);
    }

    // ===================== getTokenBalance 测试 =====================

    public function testGetTokenBalanceReturnsTokenValue(): void
    {
        $mockTron = $this->createMockTron();
        $mockTron->expects($this->once())
            ->method('getAccount')
            ->with('TTestAddress123')
            ->willReturn([
                'address' => '41testaddress',
                'assetV2' => [
                    ['key' => '1000001', 'value' => 500000],
                    ['key' => '1000002', 'value' => 300000],
                ],
            ])
        ;

        $service = new BlockchainQueryService($mockTron);
        $balance = $service->getTokenBalance(1000001, 'TTestAddress123');

        $this->assertSame(500000, $balance);
    }

    public function testGetTokenBalanceWithFromTronConvertsValue(): void
    {
        $mockTron = $this->createMockTron();
        $mockTron->expects($this->once())
            ->method('getAccount')
            ->with('TTestAddress123')
            ->willReturn([
                'assetV2' => [
                    ['key' => '1000001', 'value' => 1000000],
                ],
            ])
        ;

        $mockTron->expects($this->once())
            ->method('fromTron')
            ->with(1000000)
            ->willReturn(1.0)
        ;

        $service = new BlockchainQueryService($mockTron);
        $balance = $service->getTokenBalance(1000001, 'TTestAddress123', true);

        $this->assertSame(1.0, $balance);
    }

    public function testGetTokenBalanceReturnsZeroWhenNoAssets(): void
    {
        $mockTron = $this->createMockTron();
        $mockTron->expects($this->once())
            ->method('getAccount')
            ->with('TTestAddress123')
            ->willReturn([
                'address' => '41testaddress',
                // No assetV2 key
            ])
        ;

        $service = new BlockchainQueryService($mockTron);
        $balance = $service->getTokenBalance(1000001, 'TTestAddress123');

        $this->assertSame(0, $balance);
    }

    public function testGetTokenBalanceReturnsZeroWhenAssetV2IsEmpty(): void
    {
        $mockTron = $this->createMockTron();
        $mockTron->expects($this->once())
            ->method('getAccount')
            ->with('TTestAddress123')
            ->willReturn([
                'address' => '41testaddress',
                'assetV2' => [],
            ])
        ;

        $service = new BlockchainQueryService($mockTron);
        $balance = $service->getTokenBalance(1000001, 'TTestAddress123');

        $this->assertSame(0, $balance);
    }

    public function testGetTokenBalanceThrowsExceptionWhenTokenNotFound(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Token id not found');

        $mockTron = $this->createMockTron();
        $mockTron->expects($this->once())
            ->method('getAccount')
            ->with('TTestAddress123')
            ->willReturn([
                'assetV2' => [
                    ['key' => '1000001', 'value' => 500000],
                    ['key' => '1000002', 'value' => 300000],
                ],
            ])
        ;

        $service = new BlockchainQueryService($mockTron);
        $service->getTokenBalance(9999999, 'TTestAddress123');
    }

    public function testGetTokenBalanceThrowsExceptionWhenTokenHasNoValue(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid token value structure');

        $mockTron = $this->createMockTron();
        $mockTron->expects($this->once())
            ->method('getAccount')
            ->with('TTestAddress123')
            ->willReturn([
                'assetV2' => [
                    ['key' => '1000001'], // Missing 'value' key
                ],
            ])
        ;

        $service = new BlockchainQueryService($mockTron);
        $service->getTokenBalance(1000001, 'TTestAddress123');
    }

    // ===================== getEventResult 测试 =====================

    public function testGetEventResultWithAllParameters(): void
    {
        $mockManager = $this->createMockManager();
        $mockTron = $this->createMockTron();
        $mockTron->expects($this->once())
            ->method('getManager')
            ->willReturn($mockManager)
        ;

        $expectedEvents = [
            ['event' => 'Transfer', 'result' => ['from' => 'addr1', 'to' => 'addr2']],
            ['event' => 'Approval', 'result' => ['owner' => 'addr1', 'spender' => 'addr3']],
        ];

        $mockManager->expects($this->once())
            ->method('request')
            ->with(
                Assert::stringContains('v1/contracts/events?'),
                [],
                'get'
            )
            ->willReturn(['data' => $expectedEvents])
        ;

        $service = new BlockchainQueryService($mockTron);
        $result = $service->getEventResult(
            'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            1609459200,
            'Transfer',
            12345
        );

        $this->assertSame($expectedEvents, $result);
    }

    public function testGetEventResultWithMinimalParameters(): void
    {
        $mockManager = $this->createMockManager();
        $mockTron = $this->createMockTron();
        $mockTron->expects($this->once())
            ->method('getManager')
            ->willReturn($mockManager)
        ;

        $expectedEvents = [
            ['event' => 'Transfer', 'result' => []],
        ];

        $mockManager->expects($this->once())
            ->method('request')
            ->with(
                Assert::stringContains('v1/contracts/events?'),
                [],
                'get'
            )
            ->willReturn(['data' => $expectedEvents])
        ;

        $service = new BlockchainQueryService($mockTron);
        $result = $service->getEventResult('TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t');

        $this->assertSame($expectedEvents, $result);
    }

    public function testGetEventResultWithNullContractAddress(): void
    {
        $mockTron = $this->createMockTron();
        $service = new BlockchainQueryService($mockTron);

        // 当没有任何有效参数时，应该返回空数组
        $result = $service->getEventResult(null, 0, null, 0);

        $this->assertSame([], $result);
    }

    public function testGetEventResultReturnsEmptyArrayWhenNoData(): void
    {
        $mockManager = $this->createMockManager();
        $mockTron = $this->createMockTron();
        $mockTron->expects($this->once())
            ->method('getManager')
            ->willReturn($mockManager)
        ;

        $mockManager->expects($this->once())
            ->method('request')
            ->willReturn([]) // No 'data' key
        ;

        $service = new BlockchainQueryService($mockTron);
        $result = $service->getEventResult('TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t');

        $this->assertSame([], $result);
    }

    public function testGetEventResultReturnsEmptyArrayWhenDataIsNotArray(): void
    {
        $mockManager = $this->createMockManager();
        $mockTron = $this->createMockTron();
        $mockTron->expects($this->once())
            ->method('getManager')
            ->willReturn($mockManager)
        ;

        $mockManager->expects($this->once())
            ->method('request')
            ->willReturn(['data' => 'invalid']) // 'data' is not an array
        ;

        $service = new BlockchainQueryService($mockTron);
        $result = $service->getEventResult('TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t');

        $this->assertSame([], $result);
    }

    // ===================== getTransactionsRelated 测试 =====================

    public function testGetTransactionsRelatedDirectionTo(): void
    {
        $mockManager = $this->createMockManager();
        $mockTron = $this->createMockTron();
        $mockTron->expects($this->once())
            ->method('getManager')
            ->willReturn($mockManager)
        ;

        $expectedTransactions = [
            'data' => [
                ['txID' => 'tx1'],
                ['txID' => 'tx2'],
            ],
        ];

        $mockManager->expects($this->once())
            ->method('request')
            ->with(
                'v1/accounts/TTestAddress123/transactions/trc20?limit=30&start=0',
                [],
                'get'
            )
            ->willReturn($expectedTransactions)
        ;

        $service = new BlockchainQueryService($mockTron);
        $result = $service->getTransactionsRelated('TTestAddress123', 'to', 30, 0);

        $this->assertSame($expectedTransactions, $result);
    }

    public function testGetTransactionsRelatedDirectionFrom(): void
    {
        $mockManager = $this->createMockManager();
        $mockTron = $this->createMockTron();
        $mockTron->expects($this->once())
            ->method('getManager')
            ->willReturn($mockManager)
        ;

        $expectedTransactions = [
            'data' => [
                ['txID' => 'tx3'],
                ['txID' => 'tx4'],
            ],
        ];

        $mockManager->expects($this->once())
            ->method('request')
            ->with(
                'v1/accounts/TTestAddress123/transactions?only_from=true&limit=20&start=10',
                [],
                'get'
            )
            ->willReturn($expectedTransactions)
        ;

        $service = new BlockchainQueryService($mockTron);
        $result = $service->getTransactionsRelated('TTestAddress123', 'from', 20, 10);

        $this->assertSame($expectedTransactions, $result);
    }

    public function testGetTransactionsRelatedDirectionAll(): void
    {
        $mockManager = $this->createMockManager();
        $mockTron = $this->createMockTron();
        $mockTron->expects($this->exactly(2))
            ->method('getManager')
            ->willReturn($mockManager)
        ;

        $toTransactions = [
            'data' => [
                ['txID' => 'tx1'],
                ['txID' => 'tx2'],
            ],
        ];

        $fromTransactions = [
            'data' => [
                ['txID' => 'tx3'],
                ['txID' => 'tx4'],
            ],
        ];

        $mockManager->expects($this->exactly(2))
            ->method('request')
            ->willReturnCallback(function (string $path) use ($toTransactions, $fromTransactions) {
                if (str_contains($path, 'trc20')) {
                    return $toTransactions;
                }

                return $fromTransactions;
            })
        ;

        $service = new BlockchainQueryService($mockTron);
        $result = $service->getTransactionsRelated('TTestAddress123', 'all', 30, 0);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $data = $result['data'];
        $this->assertIsArray($data);
        $this->assertCount(4, $data);
        $this->assertIsArray($data[0]);
        $this->assertSame('tx1', $data[0]['txID']);
        $this->assertIsArray($data[1]);
        $this->assertSame('tx2', $data[1]['txID']);
        $this->assertIsArray($data[2]);
        $this->assertSame('tx3', $data[2]['txID']);
        $this->assertIsArray($data[3]);
        $this->assertSame('tx4', $data[3]['txID']);
    }

    public function testGetTransactionsRelatedDirectionAllWithMissingData(): void
    {
        $mockManager = $this->createMockManager();
        $mockTron = $this->createMockTron();
        $mockTron->expects($this->exactly(2))
            ->method('getManager')
            ->willReturn($mockManager)
        ;

        // 一个有 data，一个没有
        $mockManager->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                ['data' => [['txID' => 'tx1']]],
                [] // No 'data' key
            )
        ;

        $service = new BlockchainQueryService($mockTron);
        $result = $service->getTransactionsRelated('TTestAddress123', 'all', 30, 0);

        // 应该返回空数组，因为其中一个请求没有 data
        $this->assertSame([], $result);
    }

    public function testGetTransactionsRelatedInvalidDirection(): void
    {
        $mockTron = $this->createMockTron();
        $service = new BlockchainQueryService($mockTron);

        $result = $service->getTransactionsRelated('TTestAddress123', 'invalid', 30, 0);

        // 无效的方向应该返回空数组
        $this->assertSame([], $result);
    }

    // ===================== 边界情况和集成测试 =====================

    public function testGetTokenBalanceWithIntegerKeyInAssets(): void
    {
        $mockTron = $this->createMockTron();
        $mockTron->expects($this->once())
            ->method('getAccount')
            ->with('TTestAddress123')
            ->willReturn([
                'assetV2' => [
                    ['key' => 1000001, 'value' => 500000], // Integer key
                ],
            ])
        ;

        $service = new BlockchainQueryService($mockTron);
        $balance = $service->getTokenBalance(1000001, 'TTestAddress123');

        $this->assertSame(500000, $balance);
    }

    public function testGetEventResultBuildsCorrectQueryString(): void
    {
        $mockManager = $this->createMockManager();
        $mockTron = $this->createMockTron();
        $mockTron->expects($this->once())
            ->method('getManager')
            ->willReturn($mockManager)
        ;

        $mockManager->expects($this->once())
            ->method('request')
            ->with(
                Assert::callback(function (string $path) {
                    // 验证查询字符串包含所有参数
                    return str_contains($path, 'contract_address=')
                        && str_contains($path, 'since_timestamp=1609459200')
                        && str_contains($path, 'event_name=Transfer')
                        && str_contains($path, 'block_number=12345');
                }),
                [],
                'get'
            )
            ->willReturn(['data' => []]);

        $service = new BlockchainQueryService($mockTron);
        $service->getEventResult('TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t', 1609459200, 'Transfer', 12345);
    }
}
