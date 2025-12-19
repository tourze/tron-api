<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\TronAPI\Exception\RuntimeException;
use Tourze\TronAPI\Provider\InMemoryHttpProvider;
use Tourze\TronAPI\Service\BlockchainQueryService;
use Tourze\TronAPI\Tron;
use Tourze\TronAPI\TronManager;

/**
 * @internal
 */
#[CoversClass(BlockchainQueryService::class)]
class BlockchainQueryServiceTest extends TestCase
{
    private InMemoryHttpProvider $fullNodeProvider;

    private Tron $tron;

    private BlockchainQueryService $service;

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

        $this->service = new BlockchainQueryService($this->tron);
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
        $this->fullNodeProvider->setResponse('wallet/getaccount', [
            'address' => '41testaddress',
            'assetV2' => [
                ['key' => '1000001', 'value' => 500000],
                ['key' => '1000002', 'value' => 300000],
            ],
        ]);

        $balance = $this->service->getTokenBalance(1000001, 'TTestAddress123');

        $this->assertSame(500000, $balance);
    }

    public function testGetTokenBalanceWithFromTronConvertsValue(): void
    {
        $this->fullNodeProvider->setResponse('wallet/getaccount', [
            'assetV2' => [
                ['key' => '1000001', 'value' => 1000000],
            ],
        ]);

        $balance = $this->service->getTokenBalance(1000001, 'TTestAddress123', true);

        // fromTron converts SUN to TRX (1 TRX = 1,000,000 SUN)
        $this->assertSame(1.0, $balance);
    }

    public function testGetTokenBalanceReturnsZeroWhenNoAssets(): void
    {
        $this->fullNodeProvider->setResponse('wallet/getaccount', [
            'address' => '41testaddress',
            // No assetV2 key
        ]);

        $balance = $this->service->getTokenBalance(1000001, 'TTestAddress123');

        $this->assertSame(0, $balance);
    }

    public function testGetTokenBalanceReturnsZeroWhenAssetV2IsEmpty(): void
    {
        $this->fullNodeProvider->setResponse('wallet/getaccount', [
            'address' => '41testaddress',
            'assetV2' => [],
        ]);

        $balance = $this->service->getTokenBalance(1000001, 'TTestAddress123');

        $this->assertSame(0, $balance);
    }

    public function testGetTokenBalanceThrowsExceptionWhenTokenNotFound(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Token id not found');

        $this->fullNodeProvider->setResponse('wallet/getaccount', [
            'assetV2' => [
                ['key' => '1000001', 'value' => 500000],
                ['key' => '1000002', 'value' => 300000],
            ],
        ]);

        $this->service->getTokenBalance(9999999, 'TTestAddress123');
    }

    public function testGetTokenBalanceThrowsExceptionWhenTokenHasNoValue(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid token value structure');

        $this->fullNodeProvider->setResponse('wallet/getaccount', [
            'assetV2' => [
                ['key' => '1000001'], // Missing 'value' key
            ],
        ]);

        $this->service->getTokenBalance(1000001, 'TTestAddress123');
    }

    // ===================== getEventResult 测试 =====================

    public function testGetEventResultWithAllParameters(): void
    {
        $expectedEvents = [
            ['event' => 'Transfer', 'result' => ['from' => 'addr1', 'to' => 'addr2']],
            ['event' => 'Approval', 'result' => ['owner' => 'addr1', 'spender' => 'addr3']],
        ];

        $this->fullNodeProvider->setResponse('v1/contracts/events', ['data' => $expectedEvents]);

        $result = $this->service->getEventResult(
            'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            1609459200,
            'Transfer',
            12345
        );

        $this->assertSame($expectedEvents, $result);
    }

    public function testGetEventResultWithMinimalParameters(): void
    {
        $expectedEvents = [
            ['event' => 'Transfer', 'result' => []],
        ];

        $this->fullNodeProvider->setResponse('v1/contracts/events', ['data' => $expectedEvents]);

        $result = $this->service->getEventResult('TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t');

        $this->assertSame($expectedEvents, $result);
    }

    public function testGetEventResultWithNullContractAddress(): void
    {
        // 当没有任何有效参数时，应该返回空数组
        $result = $this->service->getEventResult(null, 0, null, 0);

        $this->assertSame([], $result);
    }

    public function testGetEventResultReturnsEmptyArrayWhenNoData(): void
    {
        $this->fullNodeProvider->setResponse('v1/contracts/events', []); // No 'data' key

        $result = $this->service->getEventResult('TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t');

        $this->assertSame([], $result);
    }

    public function testGetEventResultReturnsEmptyArrayWhenDataIsNotArray(): void
    {
        $this->fullNodeProvider->setResponse('v1/contracts/events', ['data' => 'invalid']);

        $result = $this->service->getEventResult('TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t');

        $this->assertSame([], $result);
    }

    // ===================== getTransactionsRelated 测试 =====================

    public function testGetTransactionsRelatedDirectionTo(): void
    {
        $expectedTransactions = [
            'data' => [
                ['txID' => 'tx1'],
                ['txID' => 'tx2'],
            ],
        ];

        $this->fullNodeProvider->setResponse('v1/accounts/TTestAddress123/transactions/trc20', $expectedTransactions);

        $result = $this->service->getTransactionsRelated('TTestAddress123', 'to', 30, 0);

        $this->assertSame($expectedTransactions, $result);
    }

    public function testGetTransactionsRelatedDirectionFrom(): void
    {
        $expectedTransactions = [
            'data' => [
                ['txID' => 'tx3'],
                ['txID' => 'tx4'],
            ],
        ];

        $this->fullNodeProvider->setResponse('v1/accounts/TTestAddress123/transactions', $expectedTransactions);

        $result = $this->service->getTransactionsRelated('TTestAddress123', 'from', 20, 10);

        $this->assertSame($expectedTransactions, $result);
    }

    public function testGetTransactionsRelatedDirectionAll(): void
    {
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

        $this->fullNodeProvider
            ->setResponse('v1/accounts/TTestAddress123/transactions/trc20', $toTransactions)
            ->setResponse('v1/accounts/TTestAddress123/transactions?only_from', $fromTransactions);

        $result = $this->service->getTransactionsRelated('TTestAddress123', 'all', 30, 0);

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
        $this->fullNodeProvider
            ->setResponse('v1/accounts/TTestAddress123/transactions/trc20', ['data' => [['txID' => 'tx1']]])
            ->setResponse('v1/accounts/TTestAddress123/transactions?only_from', []); // No 'data' key

        $result = $this->service->getTransactionsRelated('TTestAddress123', 'all', 30, 0);

        // 应该返回空数组，因为其中一个请求没有 data
        $this->assertSame([], $result);
    }

    public function testGetTransactionsRelatedInvalidDirection(): void
    {
        $result = $this->service->getTransactionsRelated('TTestAddress123', 'invalid', 30, 0);

        // 无效的方向应该返回空数组
        $this->assertSame([], $result);
    }

    // ===================== 边界情况测试 =====================

    public function testGetTokenBalanceWithIntegerKeyInAssets(): void
    {
        $this->fullNodeProvider->setResponse('wallet/getaccount', [
            'assetV2' => [
                ['key' => 1000001, 'value' => 500000], // Integer key
            ],
        ]);

        $balance = $this->service->getTokenBalance(1000001, 'TTestAddress123');

        $this->assertSame(500000, $balance);
    }

    public function testGetEventResultBuildsCorrectQueryString(): void
    {
        $this->fullNodeProvider->setResponse('v1/contracts/events', ['data' => []]);

        $this->service->getEventResult('TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t', 1609459200, 'Transfer', 12345);

        // 验证请求历史
        $lastRequest = $this->fullNodeProvider->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertStringContainsString('contract_address=', $lastRequest['url']);
        $this->assertStringContainsString('since_timestamp=1609459200', $lastRequest['url']);
        $this->assertStringContainsString('event_name=Transfer', $lastRequest['url']);
        $this->assertStringContainsString('block_number=12345', $lastRequest['url']);
    }
}
