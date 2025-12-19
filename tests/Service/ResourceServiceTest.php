<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\TronAPI\Exception\InvalidArgumentException;
use Tourze\TronAPI\Provider\InMemoryHttpProvider;
use Tourze\TronAPI\Service\ResourceService;
use Tourze\TronAPI\Tron;
use Tourze\TronAPI\TronManager;

/**
 * @internal
 */
#[CoversClass(ResourceService::class)]
class ResourceServiceTest extends TestCase
{
    private InMemoryHttpProvider $fullNodeProvider;

    private Tron $tron;

    private ResourceService $resourceService;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->resourceService = new ResourceService($this->tron);
    }

    public function testCanBeInstantiated(): void
    {
        $this->assertInstanceOf(ResourceService::class, $this->resourceService);
    }

    // freezeBalance() exception tests
    public function testFreezeBalanceThrowsExceptionForNullAddress(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Address not specified');

        $this->resourceService->freezeBalance(100.0, 3, 'BANDWIDTH', null);
    }

    public function testFreezeBalanceThrowsExceptionForEmptyAddress(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Address not specified');

        $this->resourceService->freezeBalance(100.0, 3, 'BANDWIDTH', '');
    }

    public function testFreezeBalanceThrowsExceptionForInvalidResource(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid resource provided: Expected "BANDWIDTH" or "ENERGY"');

        $this->resourceService->freezeBalance(100.0, 3, 'INVALID', 'TTest123Address456');
    }

    public function testFreezeBalanceThrowsExceptionForInvalidDuration(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid duration provided, minimum of 3 days');

        $this->resourceService->freezeBalance(100.0, 2, 'BANDWIDTH', 'TTest123Address456');
    }

    public function testFreezeBalanceThrowsExceptionForZeroDuration(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid duration provided, minimum of 3 days');

        $this->resourceService->freezeBalance(100.0, 0, 'BANDWIDTH', 'TTest123Address456');
    }

    public function testFreezeBalanceThrowsExceptionForNegativeDuration(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid duration provided, minimum of 3 days');

        $this->resourceService->freezeBalance(100.0, -1, 'BANDWIDTH', 'TTest123Address456');
    }

    // freezeBalance() success tests
    public function testFreezeBalanceWithBandwidthResource(): void
    {
        $expectedResponse = [
            'result' => true,
            'txID' => 'mock_tx_id',
        ];

        $this->fullNodeProvider->setResponse('wallet/freezebalance', $expectedResponse);

        $result = $this->resourceService->freezeBalance(100.0, 3, 'BANDWIDTH', 'TTest123Address456');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('result', $result);
        $this->assertTrue($result['result']);
        $this->assertArrayHasKey('txID', $result);

        // 验证请求参数
        $lastRequest = $this->fullNodeProvider->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertSame('wallet/freezebalance', $lastRequest['url']);
        $this->assertArrayHasKey('owner_address', $lastRequest['payload']);
        $this->assertArrayHasKey('frozen_balance', $lastRequest['payload']);
        $this->assertSame(100000000, $lastRequest['payload']['frozen_balance']); // 100 TRX in sun
        $this->assertSame(3, $lastRequest['payload']['frozen_duration']);
        $this->assertSame('BANDWIDTH', $lastRequest['payload']['resource']);
    }

    public function testFreezeBalanceWithEnergyResource(): void
    {
        $expectedResponse = [
            'result' => true,
            'txID' => 'mock_tx_id',
        ];

        $this->fullNodeProvider->setResponse('wallet/freezebalance', $expectedResponse);

        $result = $this->resourceService->freezeBalance(50.0, 5, 'ENERGY', 'TTest123Address456');

        $this->assertIsArray($result);
        $this->assertTrue($result['result']);

        // 验证请求参数
        $lastRequest = $this->fullNodeProvider->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertSame('ENERGY', $lastRequest['payload']['resource']);
        $this->assertSame(5, $lastRequest['payload']['frozen_duration']);
        $this->assertSame(50000000, $lastRequest['payload']['frozen_balance']); // 50 TRX in sun
    }

    public function testFreezeBalanceWithMinimumDuration(): void
    {
        $expectedResponse = ['result' => true];

        $this->fullNodeProvider->setResponse('wallet/freezebalance', $expectedResponse);

        $result = $this->resourceService->freezeBalance(100.0, 3, 'BANDWIDTH', 'TTest123Address456');

        $this->assertIsArray($result);

        // 验证最小持续时间为3天
        $lastRequest = $this->fullNodeProvider->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertSame(3, $lastRequest['payload']['frozen_duration']);
    }

    // unfreezeBalance() exception tests
    public function testUnfreezeBalanceThrowsExceptionForNullAddress(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Owner Address not specified');

        $this->resourceService->unfreezeBalance('BANDWIDTH', null);
    }

    public function testUnfreezeBalanceThrowsExceptionForEmptyAddress(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Owner Address not specified');

        $this->resourceService->unfreezeBalance('BANDWIDTH', '');
    }

    public function testUnfreezeBalanceThrowsExceptionForInvalidResource(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid resource provided: Expected "BANDWIDTH" or "ENERGY"');

        $this->resourceService->unfreezeBalance('INVALID', 'TTest123Address456');
    }

    // unfreezeBalance() success tests
    public function testUnfreezeBalanceWithBandwidthResource(): void
    {
        $expectedResponse = [
            'result' => true,
            'txID' => 'mock_unfreeze_tx_id',
        ];

        $this->fullNodeProvider->setResponse('wallet/unfreezebalance', $expectedResponse);

        $result = $this->resourceService->unfreezeBalance('BANDWIDTH', 'TTest123Address456');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('result', $result);
        $this->assertTrue($result['result']);
        $this->assertArrayHasKey('txID', $result);

        // 验证请求参数
        $lastRequest = $this->fullNodeProvider->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertSame('wallet/unfreezebalance', $lastRequest['url']);
        $this->assertArrayHasKey('owner_address', $lastRequest['payload']);
        $this->assertSame('BANDWIDTH', $lastRequest['payload']['resource']);
    }

    public function testUnfreezeBalanceWithEnergyResource(): void
    {
        $expectedResponse = [
            'result' => true,
            'txID' => 'mock_unfreeze_tx_id',
        ];

        $this->fullNodeProvider->setResponse('wallet/unfreezebalance', $expectedResponse);

        $result = $this->resourceService->unfreezeBalance('ENERGY', 'TTest123Address456');

        $this->assertIsArray($result);
        $this->assertTrue($result['result']);

        // 验证请求参数
        $lastRequest = $this->fullNodeProvider->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertSame('ENERGY', $lastRequest['payload']['resource']);
    }

    // Edge case tests
    public function testFreezeBalanceWithZeroAmount(): void
    {
        $expectedResponse = ['result' => true];

        $this->fullNodeProvider->setResponse('wallet/freezebalance', $expectedResponse);

        $result = $this->resourceService->freezeBalance(0.0, 3, 'BANDWIDTH', 'TTest123Address456');

        $this->assertIsArray($result);

        // 验证冻结金额为0
        $lastRequest = $this->fullNodeProvider->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertSame(0, $lastRequest['payload']['frozen_balance']);
    }

    public function testFreezeBalanceWithLargeAmount(): void
    {
        $expectedResponse = ['result' => true];

        $this->fullNodeProvider->setResponse('wallet/freezebalance', $expectedResponse);

        $result = $this->resourceService->freezeBalance(1000000.0, 10, 'BANDWIDTH', 'TTest123Address456');

        $this->assertIsArray($result);

        // 验证大额冻结（1,000,000 TRX = 1,000,000,000,000 SUN）
        $lastRequest = $this->fullNodeProvider->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertSame(1000000000000, $lastRequest['payload']['frozen_balance']);
    }
}
