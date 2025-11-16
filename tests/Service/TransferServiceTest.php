<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\TronAPI\Exception\InvalidArgumentException;
use Tourze\TronAPI\Service\TransferService;
use Tourze\TronAPI\Tron;
use Tourze\TronAPI\TronManager;

/**
 * @internal
 */
#[CoversClass(TransferService::class)]
class TransferServiceTest extends TestCase
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
        $service = new TransferService($tron);
        $this->assertInstanceOf(TransferService::class, $service);
    }

    // ===================== sendTrx 测试 =====================

    public function testSendTrxThrowsExceptionForNegativeAmount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid amount provided');

        $mockTron = $this->createMockTron();
        $service = new TransferService($mockTron);

        $service->sendTrx('TReceiverAddress', -100);
    }

    public function testSendTrxThrowsExceptionWhenSendingToSameAccount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot transfer TRX to the same account');

        $mockTron = $this->createMockTron();
        $mockTron->method('address2HexString')
            ->willReturn('41sameaddress')
        ;

        $service = new TransferService($mockTron);
        $service->sendTrx('TSameAddress', 100, 'TSameAddress');
    }

    public function testSendTrxUsesDefaultFromAddress(): void
    {
        $mockManager = $this->createMockManager();
        $mockTron = $this->createMockTron();

        $mockTron->address = ['hex' => '41defaultaddress'];

        $mockTron->expects($this->once())
            ->method('getManager')
            ->willReturn($mockManager)
        ;

        $mockTron->method('address2HexString')
            ->willReturnCallback(function ($addr) {
                if ('41defaultaddress' === $addr) {
                    return '41defaultaddress';
                }

                return '41receiver';
            })
        ;

        $mockTron->expects($this->once())
            ->method('toTron')
            ->with(100.0)
            ->willReturn(100000000)
        ;

        $expectedResponse = [
            'txID' => 'abc123',
            'result' => true,
        ];

        $mockManager->expects($this->once())
            ->method('request')
            ->with(
                'wallet/createtransaction',
                self::callback(function ($params) {
                    return '41defaultaddress' === $params['owner_address']
                        && '41receiver' === $params['to_address']
                        && 100000000 === $params['amount']
                        && !isset($params['extra_data']);
                })
            )
            ->willReturn($expectedResponse)
        ;

        $service = new TransferService($mockTron);
        $result = $service->sendTrx('TReceiverAddress', 100.0);

        $this->assertSame($expectedResponse, $result);
    }

    public function testSendTrxWithCustomFromAddress(): void
    {
        $mockManager = $this->createMockManager();
        $mockTron = $this->createMockTron();

        $mockTron->expects($this->once())
            ->method('getManager')
            ->willReturn($mockManager)
        ;

        $mockTron->method('address2HexString')
            ->willReturnCallback(function ($addr) {
                return 'TFromAddress' === $addr ? '41from' : '41to';
            })
        ;

        $mockTron->expects($this->once())
            ->method('toTron')
            ->with(500.0)
            ->willReturn(500000000)
        ;

        $expectedResponse = [
            'txID' => 'def456',
            'result' => true,
        ];

        $mockManager->expects($this->once())
            ->method('request')
            ->with(
                'wallet/createtransaction',
                self::callback(function ($params) {
                    return '41from' === $params['owner_address']
                        && '41to' === $params['to_address']
                        && 500000000 === $params['amount'];
                })
            )
            ->willReturn($expectedResponse)
        ;

        $service = new TransferService($mockTron);
        $result = $service->sendTrx('TToAddress', 500.0, 'TFromAddress');

        $this->assertSame($expectedResponse, $result);
    }

    public function testSendTrxWithMessage(): void
    {
        $mockManager = $this->createMockManager();
        $mockTron = $this->createMockTron();

        $mockTron->address = ['hex' => '41sender'];

        $mockTron->expects($this->once())
            ->method('getManager')
            ->willReturn($mockManager)
        ;

        $mockTron->method('address2HexString')
            ->willReturnCallback(function ($addr) {
                return '41sender' === $addr ? '41sender' : '41receiver';
            })
        ;

        $mockTron->expects($this->once())
            ->method('toTron')
            ->with(100.0)
            ->willReturn(100000000)
        ;

        $mockTron->expects($this->once())
            ->method('stringUtf8toHex')
            ->with('Test message')
            ->willReturn('54657374206d657373616765')
        ;

        $expectedResponse = [
            'txID' => 'ghi789',
            'result' => true,
        ];

        $mockManager->expects($this->once())
            ->method('request')
            ->with(
                'wallet/createtransaction',
                self::callback(function ($params) {
                    return isset($params['extra_data'])
                        && '54657374206d657373616765' === $params['extra_data'];
                })
            )
            ->willReturn($expectedResponse)
        ;

        $service = new TransferService($mockTron);
        $result = $service->sendTrx('TReceiverAddress', 100.0, null, 'Test message');

        $this->assertSame($expectedResponse, $result);
    }

    // ===================== sendToken 测试 =====================

    public function testSendTokenThrowsExceptionForZeroAmount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid amount provided');

        $mockTron = $this->createMockTron();
        $service = new TransferService($mockTron);

        $service->sendToken('TReceiverAddress', 0, 'token123');
    }

    public function testSendTokenThrowsExceptionForNegativeAmount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid amount provided');

        $mockTron = $this->createMockTron();
        $service = new TransferService($mockTron);

        $service->sendToken('TReceiverAddress', -100, 'token123');
    }

    public function testSendTokenThrowsExceptionWhenSendingToSameAccount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot transfer tokens to the same account');

        $mockTron = $this->createMockTron();
        $mockTron->address = ['hex' => '41same'];

        $service = new TransferService($mockTron);
        $service->sendToken('41same', 100, 'token123');
    }

    public function testSendTokenThrowsExceptionWhenResponseContainsError(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Insufficient token balance');

        $mockManager = $this->createMockManager();
        $mockTron = $this->createMockTron();

        $mockTron->address = ['hex' => '41sender'];

        $mockTron->expects($this->once())
            ->method('getManager')
            ->willReturn($mockManager)
        ;

        $mockTron->method('address2HexString')
            ->willReturnCallback(function ($addr) {
                return '41sender' === $addr ? '41sender' : '41receiver';
            })
        ;

        $mockTron->expects($this->once())
            ->method('stringUtf8toHex')
            ->with('token123')
            ->willReturn('746f6b656e313233')
        ;

        $mockManager->expects($this->once())
            ->method('request')
            ->willReturn(['Error' => 'Insufficient token balance'])
        ;

        $service = new TransferService($mockTron);
        $service->sendToken('TReceiverAddress', 1000, 'token123');
    }

    public function testSendTokenUsesDefaultFromAddress(): void
    {
        $mockManager = $this->createMockManager();
        $mockTron = $this->createMockTron();

        $mockTron->address = ['hex' => '41defaultsender'];

        $mockTron->expects($this->once())
            ->method('getManager')
            ->willReturn($mockManager)
        ;

        $mockTron->method('address2HexString')
            ->willReturnCallback(function ($addr) {
                return '41defaultsender' === $addr ? '41defaultsender' : '41receiver';
            })
        ;

        $mockTron->expects($this->once())
            ->method('stringUtf8toHex')
            ->with('MyToken')
            ->willReturn('4d79546f6b656e')
        ;

        $expectedResponse = [
            'txID' => 'token123',
            'result' => true,
        ];

        $mockManager->expects($this->once())
            ->method('request')
            ->with(
                'wallet/transferasset',
                self::callback(function ($params) {
                    return '41defaultsender' === $params['owner_address']
                        && '41receiver' === $params['to_address']
                        && '4d79546f6b656e' === $params['asset_name']
                        && 500 === $params['amount'];
                })
            )
            ->willReturn($expectedResponse)
        ;

        $service = new TransferService($mockTron);
        $result = $service->sendToken('TReceiverAddress', 500, 'MyToken');

        $this->assertSame($expectedResponse, $result);
    }

    public function testSendTokenWithCustomFromAddress(): void
    {
        $mockManager = $this->createMockManager();
        $mockTron = $this->createMockTron();

        $mockTron->expects($this->once())
            ->method('getManager')
            ->willReturn($mockManager)
        ;

        $mockTron->method('address2HexString')
            ->willReturnCallback(function ($addr) {
                return 'TCustomSender' === $addr ? '41customsender' : '41receiver';
            })
        ;

        $mockTron->expects($this->once())
            ->method('stringUtf8toHex')
            ->with('CustomToken')
            ->willReturn('437573746f6d546f6b656e')
        ;

        $expectedResponse = [
            'txID' => 'custom456',
            'result' => true,
        ];

        $mockManager->expects($this->once())
            ->method('request')
            ->with(
                'wallet/transferasset',
                self::callback(function ($params) {
                    return '41customsender' === $params['owner_address']
                        && '41receiver' === $params['to_address']
                        && '437573746f6d546f6b656e' === $params['asset_name']
                        && 1000 === $params['amount'];
                })
            )
            ->willReturn($expectedResponse);

        $service = new TransferService($mockTron);
        $result = $service->sendToken('TReceiverAddress', 1000, 'CustomToken', 'TCustomSender');

        $this->assertSame($expectedResponse, $result);
    }
}
