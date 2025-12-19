<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\TronAPI\Exception\InvalidArgumentException;
use Tourze\TronAPI\Provider\InMemoryHttpProvider;
use Tourze\TronAPI\Service\TransferService;
use Tourze\TronAPI\Tron;
use Tourze\TronAPI\TronManager;

/**
 * @internal
 */
#[CoversClass(TransferService::class)]
class TransferServiceTest extends TestCase
{
    private InMemoryHttpProvider $fullNodeProvider;

    private Tron $tron;

    private TransferService $service;

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

        $this->service = new TransferService($this->tron);
    }

    public function testCanBeInstantiated(): void
    {
        $tron = new Tron();
        $service = new TransferService($tron);
        $this->assertInstanceOf(TransferService::class, $service);
    }

    // ===================== sendTrx 测试 =====================

    public function testSendTrxThrowsExceptionForNegativeAmount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid amount provided');

        $this->service->sendTrx('TReceiverAddress', -100);
    }

    public function testSendTrxThrowsExceptionWhenSendingToSameAccount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot transfer TRX to the same account');

        // 使用两个相同的地址
        $sameAddress = 'TJRabPrwbZy45sbavfcjinPJC18kjpRTv8';
        $this->service->sendTrx($sameAddress, 100, $sameAddress);
    }

    public function testSendTrxUsesDefaultFromAddress(): void
    {
        // 设置默认地址
        $this->tron->address = ['hex' => '41a614f803b6fd780986a42c78ec9c7f77e6ded13c'];

        $expectedResponse = [
            'txID' => 'abc123',
            'result' => true,
        ];

        $this->fullNodeProvider->setResponse('wallet/createtransaction', $expectedResponse);

        $result = $this->service->sendTrx('TReceiverAddressXXX', 100.0);

        $this->assertSame($expectedResponse, $result);

        // 验证请求参数
        $lastRequest = $this->fullNodeProvider->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertSame('wallet/createtransaction', $lastRequest['url']);
        $this->assertArrayHasKey('owner_address', $lastRequest['payload']);
        $this->assertArrayHasKey('to_address', $lastRequest['payload']);
        $this->assertArrayHasKey('amount', $lastRequest['payload']);
        $this->assertArrayNotHasKey('extra_data', $lastRequest['payload']);
    }

    public function testSendTrxWithCustomFromAddress(): void
    {
        $expectedResponse = [
            'txID' => 'def456',
            'result' => true,
        ];

        $this->fullNodeProvider->setResponse('wallet/createtransaction', $expectedResponse);

        // 使用明确不同的地址
        $fromAddress = 'TJRabPrwbZy45sbavfcjinPJC18kjpRTv8';
        $toAddress = 'TGzz8gjYiYRqpfmDwnLxfgPuLVNmpCswVp';

        $result = $this->service->sendTrx($toAddress, 500.0, $fromAddress);

        $this->assertSame($expectedResponse, $result);

        // 验证请求参数
        $lastRequest = $this->fullNodeProvider->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertSame('wallet/createtransaction', $lastRequest['url']);
        $this->assertArrayHasKey('amount', $lastRequest['payload']);
        // 500 TRX = 500 * 1,000,000 SUN
        $this->assertSame(500000000, $lastRequest['payload']['amount']);
    }

    public function testSendTrxWithMessage(): void
    {
        $this->tron->address = ['hex' => '41a614f803b6fd780986a42c78ec9c7f77e6ded13c'];

        $expectedResponse = [
            'txID' => 'ghi789',
            'result' => true,
        ];

        $this->fullNodeProvider->setResponse('wallet/createtransaction', $expectedResponse);

        $result = $this->service->sendTrx('TReceiverAddressXXX', 100.0, null, 'Test message');

        $this->assertSame($expectedResponse, $result);

        // 验证请求包含 extra_data
        $lastRequest = $this->fullNodeProvider->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertArrayHasKey('extra_data', $lastRequest['payload']);
        // "Test message" 的 hex 表示
        $this->assertSame('54657374206d657373616765', $lastRequest['payload']['extra_data']);
    }

    // ===================== sendToken 测试 =====================

    public function testSendTokenThrowsExceptionForZeroAmount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid amount provided');

        $this->service->sendToken('TReceiverAddress', 0, 'token123');
    }

    public function testSendTokenThrowsExceptionForNegativeAmount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid amount provided');

        $this->service->sendToken('TReceiverAddress', -100, 'token123');
    }

    public function testSendTokenThrowsExceptionWhenSendingToSameAccount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot transfer tokens to the same account');

        // 设置 from 地址和 to 地址相同
        $sameAddress = '41same';
        $this->tron->address = ['hex' => $sameAddress];
        $this->service->sendToken($sameAddress, 100, 'token123');
    }

    public function testSendTokenThrowsExceptionWhenResponseContainsError(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Insufficient token balance');

        $this->tron->address = ['hex' => '41sender'];

        $this->fullNodeProvider->setResponse('wallet/transferasset', [
            'Error' => 'Insufficient token balance',
        ]);

        $this->service->sendToken('TReceiverAddress', 1000, 'token123');
    }

    public function testSendTokenUsesDefaultFromAddress(): void
    {
        $this->tron->address = ['hex' => '41defaultsender'];

        $expectedResponse = [
            'txID' => 'token123',
            'result' => true,
        ];

        $this->fullNodeProvider->setResponse('wallet/transferasset', $expectedResponse);

        $result = $this->service->sendToken('TReceiverAddress', 500, 'MyToken');

        $this->assertSame($expectedResponse, $result);

        // 验证请求参数
        $lastRequest = $this->fullNodeProvider->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertSame('wallet/transferasset', $lastRequest['url']);
        $this->assertArrayHasKey('owner_address', $lastRequest['payload']);
        $this->assertArrayHasKey('to_address', $lastRequest['payload']);
        $this->assertArrayHasKey('asset_name', $lastRequest['payload']);
        $this->assertArrayHasKey('amount', $lastRequest['payload']);
        $this->assertSame(500, $lastRequest['payload']['amount']);
        // "MyToken" 的 hex 表示
        $this->assertSame('4d79546f6b656e', $lastRequest['payload']['asset_name']);
    }

    public function testSendTokenWithCustomFromAddress(): void
    {
        $expectedResponse = [
            'txID' => 'custom456',
            'result' => true,
        ];

        $this->fullNodeProvider->setResponse('wallet/transferasset', $expectedResponse);

        // 使用不同的地址
        $fromAddress = 'TJRabPrwbZy45sbavfcjinPJC18kjpRTv8';
        $toAddress = 'TGzz8gjYiYRqpfmDwnLxfgPuLVNmpCswVp';

        $result = $this->service->sendToken($toAddress, 1000, 'CustomToken', $fromAddress);

        $this->assertSame($expectedResponse, $result);

        // 验证请求参数
        $lastRequest = $this->fullNodeProvider->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertSame('wallet/transferasset', $lastRequest['url']);
        $this->assertSame(1000, $lastRequest['payload']['amount']);
        // "CustomToken" 的 hex 表示
        $this->assertSame('437573746f6d546f6b656e', $lastRequest['payload']['asset_name']);
    }
}
