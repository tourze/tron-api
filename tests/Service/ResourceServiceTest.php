<?php

namespace Tourze\TronAPI\Tests\Service;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\TronAPI\Exception\InvalidArgumentException;
use Tourze\TronAPI\Exception\RuntimeException;
use Tourze\TronAPI\Service\ResourceService;
use Tourze\TronAPI\Tron;
use Tourze\TronAPI\TronManager;

/**
 * @internal
 */
#[CoversClass(ResourceService::class)]
class ResourceServiceTest extends TestCase
{
    private ResourceService $resourceService;

    private Tron $tron;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tron = new Tron();
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

    // freezeBalance() success tests with mocked manager
    public function testFreezeBalanceWithBandwidthResource(): void
    {
        $expectedResponse = [
            'result' => true,
            'txID' => 'mock_tx_id',
        ];

        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->with(
                $this->equalTo('wallet/freezebalance'),
                Assert::callback(function ($options) {
                    return isset($options['owner_address'])
                        && isset($options['frozen_balance'])
                        && 100000000 === $options['frozen_balance'] // 100 TRX in sun
                        && 3 === $options['frozen_duration']
                        && 'BANDWIDTH' === $options['resource'];
                })
            )
            ->willReturn($expectedResponse)
        ;

        $tron = $this->createMock(Tron::class);
        $tron->method('getManager')->willReturn($mockManager);
        $tron->method('address2HexString')->willReturn('41mock_hex_address');
        $tron->method('toTron')->willReturnCallback(function ($amount) {
            /** @var numeric-string $amountStr */
            $amountStr = (string) $amount;

            return (int) bcmul($amountStr, '1000000', 0);
        });

        $resourceService = new ResourceService($tron);
        $result = $resourceService->freezeBalance(100.0, 3, 'BANDWIDTH', 'TTest123Address456');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('result', $result);
        $this->assertTrue($result['result']);
        $this->assertArrayHasKey('txID', $result);
    }

    public function testFreezeBalanceWithEnergyResource(): void
    {
        $expectedResponse = [
            'result' => true,
            'txID' => 'mock_tx_id',
        ];

        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->with(
                $this->equalTo('wallet/freezebalance'),
                Assert::callback(function ($options) {
                    return 'ENERGY' === $options['resource']
                        && 5 === $options['frozen_duration']
                        && 50000000 === $options['frozen_balance']; // 50 TRX in sun
                })
            )
            ->willReturn($expectedResponse)
        ;

        $tron = $this->createMock(Tron::class);
        $tron->method('getManager')->willReturn($mockManager);
        $tron->method('address2HexString')->willReturn('41mock_hex_address');
        $tron->method('toTron')->willReturnCallback(function ($amount) {
            /** @var numeric-string $amountStr */
            $amountStr = (string) $amount;

            return (int) bcmul($amountStr, '1000000', 0);
        });

        $resourceService = new ResourceService($tron);
        $result = $resourceService->freezeBalance(50.0, 5, 'ENERGY', 'TTest123Address456');

        $this->assertIsArray($result);
        $this->assertTrue($result['result']);
    }

    public function testFreezeBalanceWithMinimumDuration(): void
    {
        $expectedResponse = ['result' => true];

        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->with(
                $this->equalTo('wallet/freezebalance'),
                Assert::callback(function ($options) {
                    return 3 === $options['frozen_duration'];
                })
            )
            ->willReturn($expectedResponse)
        ;

        $tron = $this->createMock(Tron::class);
        $tron->method('getManager')->willReturn($mockManager);
        $tron->method('address2HexString')->willReturn('41mock_hex_address');
        $tron->method('toTron')->willReturn(100000000);

        $resourceService = new ResourceService($tron);
        $result = $resourceService->freezeBalance(100.0, 3, 'BANDWIDTH', 'TTest123Address456');

        $this->assertIsArray($result);
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

    // unfreezeBalance() success tests with mocked manager
    public function testUnfreezeBalanceWithBandwidthResource(): void
    {
        $expectedResponse = [
            'result' => true,
            'txID' => 'mock_unfreeze_tx_id',
        ];

        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->with(
                $this->equalTo('wallet/unfreezebalance'),
                Assert::callback(function ($options) {
                    return isset($options['owner_address'])
                        && 'BANDWIDTH' === $options['resource'];
                })
            )
            ->willReturn($expectedResponse)
        ;

        $tron = $this->createMock(Tron::class);
        $tron->method('getManager')->willReturn($mockManager);
        $tron->method('address2HexString')->willReturn('41mock_hex_address');

        $resourceService = new ResourceService($tron);
        $result = $resourceService->unfreezeBalance('BANDWIDTH', 'TTest123Address456');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('result', $result);
        $this->assertTrue($result['result']);
        $this->assertArrayHasKey('txID', $result);
    }

    public function testUnfreezeBalanceWithEnergyResource(): void
    {
        $expectedResponse = [
            'result' => true,
            'txID' => 'mock_unfreeze_tx_id',
        ];

        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->with(
                $this->equalTo('wallet/unfreezebalance'),
                Assert::callback(function ($options) {
                    return 'ENERGY' === $options['resource'];
                })
            )
            ->willReturn($expectedResponse)
        ;

        $tron = $this->createMock(Tron::class);
        $tron->method('getManager')->willReturn($mockManager);
        $tron->method('address2HexString')->willReturn('41mock_hex_address');

        $resourceService = new ResourceService($tron);
        $result = $resourceService->unfreezeBalance('ENERGY', 'TTest123Address456');

        $this->assertIsArray($result);
        $this->assertTrue($result['result']);
    }

    // Edge case tests
    public function testFreezeBalanceWithZeroAmount(): void
    {
        $expectedResponse = ['result' => true];

        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->with(
                $this->equalTo('wallet/freezebalance'),
                Assert::callback(function ($options) {
                    return 0 === $options['frozen_balance'];
                })
            )
            ->willReturn($expectedResponse)
        ;

        $tron = $this->createMock(Tron::class);
        $tron->method('getManager')->willReturn($mockManager);
        $tron->method('address2HexString')->willReturn('41mock_hex_address');
        $tron->method('toTron')->willReturn(0);

        $resourceService = new ResourceService($tron);
        $result = $resourceService->freezeBalance(0.0, 3, 'BANDWIDTH', 'TTest123Address456');

        $this->assertIsArray($result);
    }

    public function testFreezeBalanceWithLargeAmount(): void
    {
        $expectedResponse = ['result' => true];

        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->with(
                $this->equalTo('wallet/freezebalance'),
                Assert::callback(function ($options) {
                    return 1000000000000 === $options['frozen_balance']; // 1,000,000 TRX
                })
            )
            ->willReturn($expectedResponse)
        ;

        $tron = $this->createMock(Tron::class);
        $tron->method('getManager')->willReturn($mockManager);
        $tron->method('address2HexString')->willReturn('41mock_hex_address');
        $tron->method('toTron')->willReturn(1000000000000);

        $resourceService = new ResourceService($tron);
        $result = $resourceService->freezeBalance(1000000.0, 10, 'BANDWIDTH', 'TTest123Address456');

        $this->assertIsArray($result);
    }
}
