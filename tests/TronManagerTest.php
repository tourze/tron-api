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
}
