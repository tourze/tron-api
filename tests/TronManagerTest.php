<?php

namespace Tourze\TronAPI\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\TronAPI\Exception\RuntimeException;
use Tourze\TronAPI\Provider\HttpProviderInterface;
use Tourze\TronAPI\Tron;
use Tourze\TronAPI\TronManager;

/**
 * @internal
 */
#[CoversClass(TronManager::class)]
class TronManagerTest extends TestCase
{
    public function testClassExists(): void
    {
        $instance = new TronManager([
            'fullNode' => null,
            'solidityNode' => null,
            'eventServer' => null,
            'signServer' => null,
        ]);
        $this->assertInstanceOf(TronManager::class, $instance);
    }

    public function testCanBeInstantiated(): void
    {
        $manager = new TronManager([
            'fullNode' => null,
            'solidityNode' => null,
            'eventServer' => null,
            'signServer' => null,
        ]);
        $this->assertInstanceOf(TronManager::class, $manager);
    }

    public function testGetProviders(): void
    {
        $manager = new TronManager([
            'fullNode' => null,
            'solidityNode' => null,
            'eventServer' => null,
            'signServer' => null,
        ]);
        $providers = $manager->getProviders();
        $this->assertIsArray($providers);
        $this->assertArrayHasKey('fullNode', $providers);
    }

    public function testFullNode(): void
    {
        $manager = new TronManager([
            'fullNode' => null,
            'solidityNode' => null,
            'eventServer' => null,
            'signServer' => null,
        ]);
        $fullNode = $manager->fullNode();
        $this->assertInstanceOf(HttpProviderInterface::class, $fullNode);
    }

    public function testSolidityNode(): void
    {
        $manager = new TronManager([
            'fullNode' => null,
            'solidityNode' => null,
            'eventServer' => null,
            'signServer' => null,
        ]);
        $solidityNode = $manager->solidityNode();
        $this->assertInstanceOf(HttpProviderInterface::class, $solidityNode);
    }

    public function testEventServer(): void
    {
        $manager = new TronManager([
            'fullNode' => null,
            'solidityNode' => null,
            'eventServer' => null,
            'signServer' => null,
        ]);
        $eventServer = $manager->eventServer();
        $this->assertInstanceOf(HttpProviderInterface::class, $eventServer);
    }

    public function testSignServer(): void
    {
        $manager = new TronManager([
            'fullNode' => null,
            'solidityNode' => null,
            'eventServer' => null,
            'signServer' => null,
        ]);
        $signServer = $manager->signServer();
        $this->assertInstanceOf(HttpProviderInterface::class, $signServer);
    }

    public function testExplorer(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('explorer is not activated');

        $manager = new TronManager([
            'fullNode' => null,
            'solidityNode' => null,
            'eventServer' => null,
            'signServer' => null,
        ]);
        $manager->explorer();
    }

    public function testIsConnected(): void
    {
        // Skip network-dependent test
        self::markTestSkipped('Network connectivity test skipped in CI/offline environment');
    }

    public function testRequestRoutesToFullNode(): void
    {
        $mockProvider = $this->createMock(HttpProviderInterface::class);
        $mockProvider->expects($this->once())
            ->method('request')
            ->with('wallet/getaccount', ['address' => 'test'], 'post')
            ->willReturn(['result' => 'success']);

        $manager = new TronManager([
            'fullNode' => $mockProvider,
            'solidityNode' => null,
            'eventServer' => null,
            'signServer' => null,
        ]);

        $result = $manager->request('wallet/getaccount', ['address' => 'test'], 'post');
        $this->assertEquals(['result' => 'success'], $result);
    }

    public function testRequestRoutesToSolidityNode(): void
    {
        $mockProvider = $this->createMock(HttpProviderInterface::class);
        $mockProvider->expects($this->once())
            ->method('request')
            ->with('walletsolidity/getaccount', ['address' => 'test'], 'post')
            ->willReturn(['result' => 'success']);

        $manager = new TronManager([
            'fullNode' => null,
            'solidityNode' => $mockProvider,
            'eventServer' => null,
            'signServer' => null,
        ]);

        $result = $manager->request('walletsolidity/getaccount', ['address' => 'test'], 'post');
        $this->assertEquals(['result' => 'success'], $result);
    }

    public function testRequestRoutesToEventServer(): void
    {
        $mockProvider = $this->createMock(HttpProviderInterface::class);
        $mockProvider->expects($this->once())
            ->method('request')
            ->with('event/gettransaction', ['txid' => 'test'], 'get')
            ->willReturn(['result' => 'success']);

        $manager = new TronManager([
            'fullNode' => null,
            'solidityNode' => null,
            'eventServer' => $mockProvider,
            'signServer' => null,
        ]);

        $result = $manager->request('event/gettransaction', ['txid' => 'test'], 'post');
        $this->assertEquals(['result' => 'success'], $result);
    }

    public function testRequestRoutesToSignServer(): void
    {
        $mockProvider = $this->createMock(HttpProviderInterface::class);
        $mockProvider->expects($this->once())
            ->method('request')
            ->with('trx-sign/sign', ['data' => 'test'], 'post')
            ->willReturn(['result' => 'success']);

        $manager = new TronManager([
            'fullNode' => null,
            'solidityNode' => null,
            'eventServer' => null,
            'signServer' => $mockProvider,
        ]);

        $result = $manager->request('trx-sign/sign', ['data' => 'test'], 'post');
        $this->assertEquals(['result' => 'success'], $result);
    }

    public function testRequestRoutesToExplorer(): void
    {
        $mockProvider = $this->createMock(HttpProviderInterface::class);
        $mockProvider->expects($this->once())
            ->method('request')
            ->with('api/system/status', [], 'get')
            ->willReturn(['result' => 'success']);

        $manager = new TronManager([
            'fullNode' => null,
            'solidityNode' => null,
            'eventServer' => null,
            'signServer' => null,
            'explorer' => $mockProvider,
        ]);

        $result = $manager->request('api/system/status', [], 'post');
        $this->assertEquals(['result' => 'success'], $result);
    }

    public function testRequestWithWalletExtensionRoutesToSolidityNode(): void
    {
        $mockProvider = $this->createMock(HttpProviderInterface::class);
        $mockProvider->expects($this->once())
            ->method('request')
            ->with('walletextension/gettransactioninfobyid', ['value' => 'test'], 'get')
            ->willReturn(['result' => 'success']);

        $manager = new TronManager([
            'fullNode' => null,
            'solidityNode' => $mockProvider,
            'eventServer' => null,
            'signServer' => null,
        ]);

        $result = $manager->request('walletextension/gettransactioninfobyid', ['value' => 'test'], 'get');
        $this->assertEquals(['result' => 'success'], $result);
    }
}
