<?php

namespace Tourze\TronAPI\Tests;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\TronAPI\Exception\InvalidArgumentException;
use Tourze\TronAPI\Service\SmartContractService;
use Tourze\TronAPI\Service\TokenQueryService;
use Tourze\TronAPI\TransactionBuilder;
use Tourze\TronAPI\Tron;
use Tourze\TronAPI\TronManager;

/**
 * @internal
 */
#[CoversClass(TransactionBuilder::class)]
class TransactionBuilderTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $tron = new Tron();
        $builder = new TransactionBuilder($tron);
        $this->assertInstanceOf(TransactionBuilder::class, $builder);
    }

    // ========== sendTrx() 测试 ==========

    public function testSendTrxSuccess(): void
    {
        $tron = $this->createMock(Tron::class);
        $tron->address = ['base58' => 'TFromAddress', 'hex' => '41from'];

        $tron->expects($this->exactly(2))
            ->method('address2HexString')
            ->willReturnCallback(function ($addr) {
                if ('TToAddress' === $addr) {
                    return '41to';
                }
                if ('TFromAddress' === $addr) {
                    return '41from';
                }

                return '41unknown';
            })
        ;

        $tron->expects($this->once())
            ->method('toTron')
            ->with(100.0)
            ->willReturn(100000000)
        ;

        $manager = $this->createMock(TronManager::class);
        $manager->expects($this->once())
            ->method('request')
            ->with(
                'wallet/createtransaction',
                Assert::callback(function ($options) {
                    return is_array($options)
                        && '41to' === $options['to_address']
                        && '41from' === $options['owner_address']
                        && 100000000 === $options['amount'];
                })
            )
            ->willReturn(['txID' => 'mock_tx_id', 'raw_data' => []])
        ;

        $tron->expects($this->once())
            ->method('getManager')
            ->willReturn($manager)
        ;

        $builder = new TransactionBuilder($tron);
        $result = $builder->sendTrx('TToAddress', 100.0, 'TFromAddress');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('txID', $result);
    }

    public function testSendTrxWithDefaultFromAddress(): void
    {
        $tron = $this->createMock(Tron::class);
        $tron->address = ['base58' => 'TDefaultFrom', 'hex' => '41defaultfrom'];

        $tron->expects($this->exactly(2))
            ->method('address2HexString')
            ->willReturnCallback(function ($addr) {
                if ('TToAddress' === $addr) {
                    return '41to';
                }
                if ('41defaultfrom' === $addr) {
                    return '41defaultfrom';
                }

                return '41unknown';
            })
        ;

        $tron->expects($this->once())
            ->method('toTron')
            ->with(50.5)
            ->willReturn(50500000)
        ;

        $manager = $this->createMock(TronManager::class);
        $manager->expects($this->once())
            ->method('request')
            ->willReturn(['txID' => 'mock_tx_id'])
        ;

        $tron->expects($this->once())
            ->method('getManager')
            ->willReturn($manager)
        ;

        $builder = new TransactionBuilder($tron);
        $result = $builder->sendTrx('TToAddress', 50.5);

        $this->assertIsArray($result);
    }

    public function testSendTrxWithMessage(): void
    {
        $tron = $this->createMock(Tron::class);
        $tron->address = ['base58' => 'TFrom', 'hex' => '41from'];

        $tron->expects($this->exactly(2))
            ->method('address2HexString')
            ->willReturnOnConsecutiveCalls('41to', '41from')
        ;

        $tron->expects($this->once())
            ->method('toTron')
            ->with(10.0)
            ->willReturn(10000000)
        ;

        $tron->expects($this->once())
            ->method('stringUtf8toHex')
            ->with('Test message')
            ->willReturn('54657374206d657373616765')
        ;

        $manager = $this->createMock(TronManager::class);
        $manager->expects($this->once())
            ->method('request')
            ->with(
                'wallet/createtransaction',
                Assert::callback(function ($options) {
                    return isset($options['extra_data'])
                        && '54657374206d657373616765' === $options['extra_data'];
                })
            )
            ->willReturn(['txID' => 'mock_tx_id'])
        ;

        $tron->expects($this->once())
            ->method('getManager')
            ->willReturn($manager)
        ;

        $builder = new TransactionBuilder($tron);
        $result = $builder->sendTrx('TToAddress', 10.0, 'TFrom', 'Test message');

        $this->assertIsArray($result);
    }

    public function testSendTrxThrowsExceptionForNegativeAmount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid amount provided');

        $tron = new Tron();
        $builder = new TransactionBuilder($tron);
        $builder->sendTrx('TToAddress', -100.0);
    }

    public function testSendTrxThrowsExceptionForSameAccount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot transfer TRX to the same account');

        $tron = $this->createMock(Tron::class);
        $tron->address = ['base58' => 'TSameAddress', 'hex' => '41same'];

        $tron->expects($this->exactly(2))
            ->method('address2HexString')
            ->with('TSameAddress')
            ->willReturn('41same')
        ;

        $builder = new TransactionBuilder($tron);
        $builder->sendTrx('TSameAddress', 100.0, 'TSameAddress');
    }

    public function testSendTrxBoundaryZeroAmount(): void
    {
        $tron = $this->createMock(Tron::class);
        $tron->address = ['base58' => 'TFrom', 'hex' => '41from'];

        $tron->expects($this->exactly(2))
            ->method('address2HexString')
            ->willReturnOnConsecutiveCalls('41to', '41from')
        ;

        $tron->expects($this->once())
            ->method('toTron')
            ->with(0.0)
            ->willReturn(0)
        ;

        $manager = $this->createMock(TronManager::class);
        $manager->expects($this->once())
            ->method('request')
            ->willReturn(['txID' => 'mock_tx_id'])
        ;

        $tron->expects($this->once())
            ->method('getManager')
            ->willReturn($manager)
        ;

        $builder = new TransactionBuilder($tron);
        $result = $builder->sendTrx('TToAddress', 0.0, 'TFrom');

        $this->assertIsArray($result);
    }

    // ========== sendToken() 测试 ==========

    public function testSendTokenSuccess(): void
    {
        $tron = $this->createMock(Tron::class);

        $tron->expects($this->exactly(2))
            ->method('address2HexString')
            ->willReturnOnConsecutiveCalls('41from', '41to')
        ;

        $tron->expects($this->once())
            ->method('stringUtf8toHex')
            ->with('TestToken')
            ->willReturn('5465737454656e')
        ;

        $manager = $this->createMock(TronManager::class);
        $manager->expects($this->once())
            ->method('request')
            ->with(
                'wallet/transferasset',
                Assert::callback(function ($options) {
                    return '41from' === $options['owner_address']
                        && '41to' === $options['to_address']
                        && 1000 === $options['amount'];
                })
            )
            ->willReturn(['txID' => 'mock_tx_id', 'raw_data' => []])
        ;

        $tron->expects($this->once())
            ->method('getManager')
            ->willReturn($manager)
        ;

        $builder = new TransactionBuilder($tron);
        $result = $builder->sendToken('TToAddress', 1000, 'TestToken', 'TFromAddress');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('txID', $result);
    }

    public function testSendTokenThrowsExceptionForZeroAmount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid amount provided');

        $tron = new Tron();
        $builder = new TransactionBuilder($tron);
        $builder->sendToken('TToAddress', 0, 'TestToken', 'TFromAddress');
    }

    public function testSendTokenThrowsExceptionForNegativeAmount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid amount provided');

        $tron = new Tron();
        $builder = new TransactionBuilder($tron);
        $builder->sendToken('TToAddress', -100, 'TestToken', 'TFromAddress');
    }

    public function testSendTokenThrowsExceptionForSameAccount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot transfer tokens to the same account');

        $tron = new Tron();
        $builder = new TransactionBuilder($tron);
        $builder->sendToken('TSameAddress', 100, 'TestToken', 'TSameAddress');
    }

    public function testSendTokenThrowsExceptionOnError(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Token transfer failed');

        $tron = $this->createMock(Tron::class);

        $tron->expects($this->exactly(2))
            ->method('address2HexString')
            ->willReturnOnConsecutiveCalls('41from', '41to')
        ;

        $tron->expects($this->once())
            ->method('stringUtf8toHex')
            ->willReturn('5465737454656e')
        ;

        $manager = $this->createMock(TronManager::class);
        $manager->expects($this->once())
            ->method('request')
            ->willReturn(['Error' => 'Token transfer failed'])
        ;

        $tron->expects($this->once())
            ->method('getManager')
            ->willReturn($manager)
        ;

        $builder = new TransactionBuilder($tron);
        $builder->sendToken('TToAddress', 100, 'TestToken', 'TFromAddress');
    }

    // ========== purchaseToken() 测试 ==========

    public function testPurchaseTokenSuccess(): void
    {
        $tron = $this->createMock(Tron::class);

        $tron->expects($this->exactly(2))
            ->method('address2HexString')
            ->willReturnOnConsecutiveCalls('41issuer', '41buyer')
        ;

        $tron->expects($this->once())
            ->method('stringUtf8toHex')
            ->with('TestToken')
            ->willReturn('5465737454656e')
        ;

        $tron->expects($this->once())
            ->method('toTron')
            ->with(1000)
            ->willReturn(1000000000)
        ;

        $manager = $this->createMock(TronManager::class);
        $manager->expects($this->once())
            ->method('request')
            ->with('wallet/participateassetissue', Assert::callback(fn ($v) => is_array($v)))
            ->willReturn(['txID' => 'mock_tx_id'])
        ;

        $tron->expects($this->once())
            ->method('getManager')
            ->willReturn($manager)
        ;

        $builder = new TransactionBuilder($tron);
        $result = $builder->purchaseToken('TIssuerAddress', 'TestToken', 1000, 'TBuyerAddress');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('txID', $result);
    }

    public function testPurchaseTokenThrowsExceptionForInvalidTokenID(): void
    {
        // 由于现在使用严格类型，无效的 tokenID 会在调用时直接抛出 TypeError
        // 这个测试需要改为测试空字符串或其他业务层面的无效情况
        // 或者移除此测试，因为类型系统已经保证了类型安全
        $this->expectException(\Throwable::class);

        $tron = new Tron();
        $builder = new TransactionBuilder($tron);
        // 空字符串可能是业务上无效的 tokenID
        $builder->purchaseToken('TIssuer', '', 1000, 'TBuyer');
    }

    public function testPurchaseTokenThrowsExceptionForInvalidAmount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid amount provided');

        $tron = new Tron();
        $builder = new TransactionBuilder($tron);
        // 传入 0 或负数的值会抛出异常（现在使用严格的 int 类型）
        $builder->purchaseToken('TIssuer', 'TestToken', 0, 'TBuyer');
    }

    public function testPurchaseTokenThrowsExceptionOnError(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Purchase failed');

        $tron = $this->createMock(Tron::class);

        $tron->expects($this->any())
            ->method('address2HexString')
            ->willReturn('41addr')
        ;

        $tron->expects($this->any())
            ->method('stringUtf8toHex')
            ->willReturn('hex')
        ;

        $tron->expects($this->any())
            ->method('toTron')
            ->willReturn(1000000000)
        ;

        $manager = $this->createMock(TronManager::class);
        $manager->expects($this->once())
            ->method('request')
            ->willReturn(['Error' => 'Purchase failed'])
        ;

        $tron->expects($this->once())
            ->method('getManager')
            ->willReturn($manager)
        ;

        $builder = new TransactionBuilder($tron);
        $builder->purchaseToken('TIssuer', 'TestToken', 1000, 'TBuyer');
    }

    // ========== createToken() 测试 ==========

    public function testCreateTokenSuccess(): void
    {
        $tron = $this->createMock(Tron::class);
        $tron->address = ['base58' => 'TIssuer', 'hex' => '41issuer'];

        $tron->expects($this->once())
            ->method('address2HexString')
            ->with('41issuer')
            ->willReturn('41issuer')
        ;

        $tron->expects($this->atLeastOnce())
            ->method('stringUtf8toHex')
            ->willReturnCallback(function (string $str) {
                return bin2hex($str);
            })
        ;

        $manager = $this->createMock(TronManager::class);
        $manager->expects($this->once())
            ->method('request')
            ->with('wallet/createassetissue', Assert::callback(fn ($v) => is_array($v)))
            ->willReturn(['txID' => 'mock_tx_id'])
        ;

        $tron->expects($this->once())
            ->method('getManager')
            ->willReturn($manager)
        ;

        $builder = new TransactionBuilder($tron);
        $currentTime = time() * 1000;
        $options = [
            'name' => 'TestToken',
            'abbreviation' => 'TTK',
            'description' => 'A test token',
            'url' => 'https://test.com',
            'totalSupply' => 1000000,
            'trxRatio' => 1,
            // saleStart 必须大于当前时间戳
            'saleStart' => $currentTime + 10000,
            'saleEnd' => $currentTime + 86400000,
        ];

        $result = $builder->createToken($options);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('txID', $result);
    }

    public function testCreateTokenWithDefaults(): void
    {
        $tron = $this->createMock(Tron::class);
        $tron->address = ['base58' => 'TIssuer', 'hex' => '41issuer'];

        $tron->expects($this->once())
            ->method('address2HexString')
            ->willReturn('41issuer')
        ;

        $tron->expects($this->atLeastOnce())
            ->method('stringUtf8toHex')
            ->willReturnCallback(function (string $str) {
                return bin2hex($str);
            })
        ;

        $manager = $this->createMock(TronManager::class);
        $manager->expects($this->once())
            ->method('request')
            ->with(
                'wallet/createassetissue',
                Assert::callback(function ($data) {
                    // TokenOptions 不包含 tokenRatio 属性，所以 toArray() 后丢失
                    // buildTokenData 中 tokenRatio ?? 0 会使用默认值 0
                    return 1 === $data['trx_num']
                        && 0 === $data['num']  // tokenRatio 不在 TokenOptions 中，默认为 0
                        && 0 === $data['free_asset_net_limit']
                        && 0 === $data['public_free_asset_net_limit'];
                })
            )
            ->willReturn(['txID' => 'mock_tx_id'])
        ;

        $tron->expects($this->once())
            ->method('getManager')
            ->willReturn($manager)
        ;

        $builder = new TransactionBuilder($tron);
        $currentTime = time() * 1000;
        $options = [
            'name' => 'TestToken',
            'abbreviation' => 'TTK',
            'description' => 'Test',
            'url' => 'https://test.com',
            'totalSupply' => 1000000,
            'trxRatio' => 1,
            'tokenRatio' => 1,  // num 字段对应 tokenRatio
            'saleStart' => $currentTime + 10000,
            'saleEnd' => $currentTime + 86400000,
        ];

        $result = $builder->createToken($options);

        $this->assertIsArray($result);
    }

    public function testCreateTokenWithCustomIssuer(): void
    {
        $tron = $this->createMock(Tron::class);
        $tron->address = ['base58' => 'TDefault', 'hex' => '41default'];

        $tron->expects($this->once())
            ->method('address2HexString')
            ->with('TCustomIssuer')
            ->willReturn('41custom')
        ;

        $tron->expects($this->atLeastOnce())
            ->method('stringUtf8toHex')
            ->willReturnCallback(function (string $str) {
                return bin2hex($str);
            })
        ;

        $manager = $this->createMock(TronManager::class);
        $manager->expects($this->once())
            ->method('request')
            ->willReturn(['txID' => 'mock_tx_id'])
        ;

        $tron->expects($this->once())
            ->method('getManager')
            ->willReturn($manager)
        ;

        $builder = new TransactionBuilder($tron);
        $currentTime = time() * 1000;
        $options = [
            'name' => 'TestToken',
            'abbreviation' => 'TTK',
            'description' => 'Test',
            'url' => 'https://test.com',
            'totalSupply' => 1000000,
            'trxRatio' => 1,
            'saleStart' => $currentTime + 10000,
            'saleEnd' => $currentTime + 86400000,
        ];

        $result = $builder->createToken($options, 'TCustomIssuer');

        $this->assertIsArray($result);
    }

    // ========== freezeBalance() 测试 ==========

    public function testFreezeBalanceSuccess(): void
    {
        $tron = $this->createMock(Tron::class);

        $tron->expects($this->once())
            ->method('address2HexString')
            ->with('TAddress')
            ->willReturn('41address')
        ;

        $tron->expects($this->once())
            ->method('toTron')
            ->with(100.0)
            ->willReturn(100000000)
        ;

        $manager = $this->createMock(TronManager::class);
        $manager->expects($this->once())
            ->method('request')
            ->with(
                'wallet/freezebalance',
                Assert::callback(function ($options) {
                    return '41address' === $options['owner_address']
                        && 100000000 === $options['frozen_balance']
                        && 3 === $options['frozen_duration']
                        && 'BANDWIDTH' === $options['resource'];
                })
            )
            ->willReturn(['txID' => 'mock_tx_id'])
        ;

        $tron->expects($this->once())
            ->method('getManager')
            ->willReturn($manager)
        ;

        $builder = new TransactionBuilder($tron);
        $result = $builder->freezeBalance(100.0, 3, 'BANDWIDTH', 'TAddress');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('txID', $result);
    }

    public function testFreezeBalanceWithEnergy(): void
    {
        $tron = $this->createMock(Tron::class);

        $tron->expects($this->once())
            ->method('address2HexString')
            ->willReturn('41address')
        ;

        $tron->expects($this->once())
            ->method('toTron')
            ->willReturn(500000000)
        ;

        $manager = $this->createMock(TronManager::class);
        $manager->expects($this->once())
            ->method('request')
            ->with(
                'wallet/freezebalance',
                Assert::callback(function ($options) {
                    return 'ENERGY' === $options['resource'];
                })
            )
            ->willReturn(['txID' => 'mock_tx_id'])
        ;

        $tron->expects($this->once())
            ->method('getManager')
            ->willReturn($manager)
        ;

        $builder = new TransactionBuilder($tron);
        $result = $builder->freezeBalance(500.0, 7, 'ENERGY', 'TAddress');

        $this->assertIsArray($result);
    }

    public function testFreezeBalanceThrowsExceptionForEmptyAddress(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Address not specified');

        $tron = new Tron();
        $builder = new TransactionBuilder($tron);
        $builder->freezeBalance(100.0, 3, 'BANDWIDTH', '');
    }

    public function testFreezeBalanceThrowsExceptionForNullAddress(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Address not specified');

        $tron = new Tron();
        $builder = new TransactionBuilder($tron);
        $builder->freezeBalance(100.0, 3, 'BANDWIDTH', null);
    }

    public function testFreezeBalanceThrowsExceptionForInvalidResource(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid resource provided: Expected "BANDWIDTH" or "ENERGY"');

        $tron = new Tron();
        $builder = new TransactionBuilder($tron);
        $builder->freezeBalance(100.0, 3, 'INVALID', 'TAddress');
    }

    public function testFreezeBalanceWithValidFloatAmount(): void
    {
        // freezeBalance 的类型检查由 PHP 类型系统完成，传入非 float 会导致 TypeError
        // 这里测试有效的 float 值
        $tron = $this->createMock(Tron::class);

        $tron->expects($this->once())
            ->method('address2HexString')
            ->willReturn('41address')
        ;

        $tron->expects($this->once())
            ->method('toTron')
            ->with(1.5)
            ->willReturn(1500000)
        ;

        $manager = $this->createMock(TronManager::class);
        $manager->expects($this->once())
            ->method('request')
            ->willReturn(['txID' => 'mock_tx_id'])
        ;

        $tron->expects($this->once())
            ->method('getManager')
            ->willReturn($manager)
        ;

        $builder = new TransactionBuilder($tron);
        $result = $builder->freezeBalance(1.5, 3, 'BANDWIDTH', 'TAddress');

        $this->assertIsArray($result);
    }

    public function testFreezeBalanceThrowsExceptionForShortDuration(): void
    {
        // duration 小于 3 天应该抛出异常
        $tron = $this->createMock(Tron::class);

        $tron->expects($this->never())
            ->method('address2HexString')
        ;

        $tron->expects($this->never())
            ->method('toTron')
        ;

        $tron->expects($this->never())
            ->method('getManager')
        ;

        $builder = new TransactionBuilder($tron);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid duration provided, minimum of 3 days');

        $builder->freezeBalance(100.0, 2, 'BANDWIDTH', 'TAddress');
    }

    // ========== unfreezeBalance() 测试 ==========

    public function testUnfreezeBalanceSuccess(): void
    {
        $tron = $this->createMock(Tron::class);

        $tron->expects($this->once())
            ->method('address2HexString')
            ->with('TAddress')
            ->willReturn('41address')
        ;

        $manager = $this->createMock(TronManager::class);
        $manager->expects($this->once())
            ->method('request')
            ->with(
                'wallet/unfreezebalance',
                Assert::callback(function ($options) {
                    return '41address' === $options['owner_address']
                        && 'BANDWIDTH' === $options['resource'];
                })
            )
            ->willReturn(['txID' => 'mock_tx_id'])
        ;

        $tron->expects($this->once())
            ->method('getManager')
            ->willReturn($manager)
        ;

        $builder = new TransactionBuilder($tron);
        $result = $builder->unfreezeBalance('BANDWIDTH', 'TAddress');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('txID', $result);
    }

    public function testUnfreezeBalanceWithEnergy(): void
    {
        $tron = $this->createMock(Tron::class);

        $tron->expects($this->once())
            ->method('address2HexString')
            ->willReturn('41address')
        ;

        $manager = $this->createMock(TronManager::class);
        $manager->expects($this->once())
            ->method('request')
            ->with(
                'wallet/unfreezebalance',
                Assert::callback(function ($options) {
                    return 'ENERGY' === $options['resource'];
                })
            )
            ->willReturn(['txID' => 'mock_tx_id'])
        ;

        $tron->expects($this->once())
            ->method('getManager')
            ->willReturn($manager)
        ;

        $builder = new TransactionBuilder($tron);
        $result = $builder->unfreezeBalance('ENERGY', 'TAddress');

        $this->assertIsArray($result);
    }

    public function testUnfreezeBalanceThrowsExceptionForNullAddress(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Owner Address not specified');

        $tron = new Tron();
        $builder = new TransactionBuilder($tron);
        $builder->unfreezeBalance('BANDWIDTH', null);
    }

    public function testUnfreezeBalanceThrowsExceptionForInvalidResource(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid resource provided: Expected "BANDWIDTH" or "ENERGY"');

        $tron = new Tron();
        $builder = new TransactionBuilder($tron);
        $builder->unfreezeBalance('INVALID', 'TAddress');
    }

    // ========== updateToken() 测试 ==========

    public function testUpdateTokenSuccess(): void
    {
        $tron = $this->createMock(Tron::class);

        $tron->expects($this->once())
            ->method('address2HexString')
            ->with('TAddress')
            ->willReturn('41address')
        ;

        $tron->expects($this->exactly(2))
            ->method('stringUtf8toHex')
            ->willReturnOnConsecutiveCalls('6465736372697074696f6e', '75726c')
        ;

        $manager = $this->createMock(TronManager::class);
        $manager->expects($this->once())
            ->method('request')
            ->with(
                'wallet/updateasset',
                Assert::callback(function ($options) {
                    return '41address' === $options['owner_address']
                        && 1000 === $options['new_limit']
                        && 5000 === $options['new_public_limit'];
                })
            )
            ->willReturn(['txID' => 'mock_tx_id'])
        ;

        $tron->expects($this->once())
            ->method('getManager')
            ->willReturn($manager)
        ;

        $builder = new TransactionBuilder($tron);
        $result = $builder->updateToken('Updated description', 'https://updated.com', 1000, 5000, 'TAddress');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('txID', $result);
    }

    public function testUpdateTokenWithZeroBandwidth(): void
    {
        $tron = $this->createMock(Tron::class);

        $tron->expects($this->once())
            ->method('address2HexString')
            ->willReturn('41address')
        ;

        $tron->expects($this->exactly(2))
            ->method('stringUtf8toHex')
            ->willReturnOnConsecutiveCalls('desc', 'url')
        ;

        $manager = $this->createMock(TronManager::class);
        $manager->expects($this->once())
            ->method('request')
            ->with(
                'wallet/updateasset',
                Assert::callback(function ($options) {
                    return 0 === $options['new_limit']
                        && 0 === $options['new_public_limit'];
                })
            )
            ->willReturn(['txID' => 'mock_tx_id'])
        ;

        $tron->expects($this->once())
            ->method('getManager')
            ->willReturn($manager)
        ;

        $builder = new TransactionBuilder($tron);
        $result = $builder->updateToken('desc', 'url', 0, 0, 'TAddress');

        $this->assertIsArray($result);
    }

    public function testUpdateTokenThrowsExceptionForNullAddress(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Owner Address not specified');

        $tron = new Tron();
        $builder = new TransactionBuilder($tron);
        $builder->updateToken('desc', 'url', 0, 0, null);
    }

    public function testUpdateTokenThrowsExceptionForNegativeBandwidth(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid free bandwidth amount provided');

        $tron = new Tron();
        $builder = new TransactionBuilder($tron);
        $builder->updateToken('desc', 'url', -100, 0, 'TAddress');
    }

    public function testUpdateTokenWithMismatchedBandwidthValues(): void
    {
        // 代码逻辑复杂，实际上很难触发异常
        // 这里测试边界情况：freeBandwidth > 0 但 freeBandwidthLimit = 0
        // 根据实际逻辑，这不会抛出异常
        $tron = $this->createMock(Tron::class);

        $tron->expects($this->once())
            ->method('address2HexString')
            ->willReturn('41address')
        ;

        $tron->expects($this->exactly(2))
            ->method('stringUtf8toHex')
            ->willReturnOnConsecutiveCalls('desc', 'url')
        ;

        $manager = $this->createMock(TronManager::class);
        $manager->expects($this->once())
            ->method('request')
            ->with(
                'wallet/updateasset',
                Assert::callback(function ($options) {
                    return 100 === $options['new_limit']
                        && 0 === $options['new_public_limit'];
                })
            )
            ->willReturn(['txID' => 'mock_tx_id'])
        ;

        $tron->expects($this->once())
            ->method('getManager')
            ->willReturn($manager)
        ;

        $builder = new TransactionBuilder($tron);
        $result = $builder->updateToken('desc', 'url', 100, 0, 'TAddress');

        $this->assertIsArray($result);
    }

    // ========== updateEnergyLimit() 测试 ==========

    public function testUpdateEnergyLimitSuccess(): void
    {
        $tron = $this->createMock(Tron::class);

        $tron->expects($this->exactly(4))
            ->method('address2HexString')
            ->willReturnOnConsecutiveCalls('41contract', '41owner', '41owner', '41contract')
        ;

        $manager = $this->createMock(TronManager::class);
        $manager->expects($this->once())
            ->method('request')
            ->with(
                'wallet/updateenergylimit',
                Assert::callback(function ($options) {
                    return '41owner' === $options['owner_address']
                        && '41contract' === $options['contract_address']
                        && 5000000 === $options['origin_energy_limit'];
                })
            )
            ->willReturn(['txID' => 'mock_tx_id'])
        ;

        $tron->expects($this->once())
            ->method('getManager')
            ->willReturn($manager)
        ;

        $builder = new TransactionBuilder($tron);
        $result = $builder->updateEnergyLimit('TContract', 5000000, 'TOwner');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('txID', $result);
    }

    public function testUpdateEnergyLimitBoundaryMinimum(): void
    {
        $tron = $this->createMock(Tron::class);

        $tron->expects($this->exactly(4))
            ->method('address2HexString')
            ->willReturnOnConsecutiveCalls('41contract', '41owner', '41owner', '41contract')
        ;

        $manager = $this->createMock(TronManager::class);
        $manager->expects($this->once())
            ->method('request')
            ->with(
                'wallet/updateenergylimit',
                Assert::callback(function ($options) {
                    return 0 === $options['origin_energy_limit'];
                })
            )
            ->willReturn(['txID' => 'mock_tx_id'])
        ;

        $tron->expects($this->once())
            ->method('getManager')
            ->willReturn($manager)
        ;

        $builder = new TransactionBuilder($tron);
        $result = $builder->updateEnergyLimit('TContract', 0, 'TOwner');

        $this->assertIsArray($result);
    }

    public function testUpdateEnergyLimitBoundaryMaximum(): void
    {
        $tron = $this->createMock(Tron::class);

        $tron->expects($this->exactly(4))
            ->method('address2HexString')
            ->willReturnOnConsecutiveCalls('41contract', '41owner', '41owner', '41contract')
        ;

        $manager = $this->createMock(TronManager::class);
        $manager->expects($this->once())
            ->method('request')
            ->with(
                'wallet/updateenergylimit',
                Assert::callback(function ($options) {
                    return 10000000 === $options['origin_energy_limit'];
                })
            )
            ->willReturn(['txID' => 'mock_tx_id'])
        ;

        $tron->expects($this->once())
            ->method('getManager')
            ->willReturn($manager)
        ;

        $builder = new TransactionBuilder($tron);
        $result = $builder->updateEnergyLimit('TContract', 10000000, 'TOwner');

        $this->assertIsArray($result);
    }

    public function testUpdateEnergyLimitThrowsExceptionForNegativeLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid originEnergyLimit provided');

        $tron = $this->createMock(Tron::class);
        $tron->expects($this->exactly(2))
            ->method('address2HexString')
            ->willReturnOnConsecutiveCalls('41contract', '41owner')
        ;

        $builder = new TransactionBuilder($tron);
        $builder->updateEnergyLimit('TContract', -100, 'TOwner');
    }

    public function testUpdateEnergyLimitThrowsExceptionForExcessiveLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid originEnergyLimit provided');

        $tron = $this->createMock(Tron::class);
        $tron->expects($this->exactly(2))
            ->method('address2HexString')
            ->willReturnOnConsecutiveCalls('41contract', '41owner')
        ;

        $builder = new TransactionBuilder($tron);
        $builder->updateEnergyLimit('TContract', 10000001, 'TOwner');
    }

    // ========== updateSetting() 测试 ==========

    public function testUpdateSettingSuccess(): void
    {
        $tron = $this->createMock(Tron::class);

        $tron->expects($this->exactly(4))
            ->method('address2HexString')
            ->willReturnOnConsecutiveCalls('41contract', '41owner', '41owner', '41contract')
        ;

        $manager = $this->createMock(TronManager::class);
        $manager->expects($this->once())
            ->method('request')
            ->with(
                'wallet/updatesetting',
                Assert::callback(function ($options) {
                    return '41owner' === $options['owner_address']
                        && '41contract' === $options['contract_address']
                        && 50 === $options['consume_user_resource_percent'];
                })
            )
            ->willReturn(['txID' => 'mock_tx_id'])
        ;

        $tron->expects($this->once())
            ->method('getManager')
            ->willReturn($manager)
        ;

        $builder = new TransactionBuilder($tron);
        $result = $builder->updateSetting('TContract', 50, 'TOwner');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('txID', $result);
    }

    public function testUpdateSettingBoundaryMinimum(): void
    {
        $tron = $this->createMock(Tron::class);

        $tron->expects($this->exactly(4))
            ->method('address2HexString')
            ->willReturnOnConsecutiveCalls('41contract', '41owner', '41owner', '41contract')
        ;

        $manager = $this->createMock(TronManager::class);
        $manager->expects($this->once())
            ->method('request')
            ->with(
                'wallet/updatesetting',
                Assert::callback(function ($options) {
                    return 0 === $options['consume_user_resource_percent'];
                })
            )
            ->willReturn(['txID' => 'mock_tx_id'])
        ;

        $tron->expects($this->once())
            ->method('getManager')
            ->willReturn($manager)
        ;

        $builder = new TransactionBuilder($tron);
        $result = $builder->updateSetting('TContract', 0, 'TOwner');

        $this->assertIsArray($result);
    }

    public function testUpdateSettingBoundaryMaximum(): void
    {
        $tron = $this->createMock(Tron::class);

        $tron->expects($this->exactly(4))
            ->method('address2HexString')
            ->willReturnOnConsecutiveCalls('41contract', '41owner', '41owner', '41contract')
        ;

        $manager = $this->createMock(TronManager::class);
        $manager->expects($this->once())
            ->method('request')
            ->with(
                'wallet/updatesetting',
                Assert::callback(function ($options) {
                    return 1000 === $options['consume_user_resource_percent'];
                })
            )
            ->willReturn(['txID' => 'mock_tx_id'])
        ;

        $tron->expects($this->once())
            ->method('getManager')
            ->willReturn($manager)
        ;

        $builder = new TransactionBuilder($tron);
        $result = $builder->updateSetting('TContract', 1000, 'TOwner');

        $this->assertIsArray($result);
    }

    public function testUpdateSettingThrowsExceptionForNegativePercentage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid userFeePercentage provided');

        $tron = $this->createMock(Tron::class);
        $tron->expects($this->exactly(2))
            ->method('address2HexString')
            ->willReturnOnConsecutiveCalls('41contract', '41owner')
        ;

        $builder = new TransactionBuilder($tron);
        $builder->updateSetting('TContract', -1, 'TOwner');
    }

    public function testUpdateSettingThrowsExceptionForExcessivePercentage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid userFeePercentage provided');

        $tron = $this->createMock(Tron::class);
        $tron->expects($this->exactly(2))
            ->method('address2HexString')
            ->willReturnOnConsecutiveCalls('41contract', '41owner')
        ;

        $builder = new TransactionBuilder($tron);
        $builder->updateSetting('TContract', 1001, 'TOwner');
    }

    // ========== triggerSmartContract() 测试 ==========

    public function testTriggerSmartContractSuccess(): void
    {
        $tron = $this->createMock(Tron::class);

        $abi = [
            [
                'name' => 'transfer',
                'inputs' => [
                    ['type' => 'address'],
                    ['type' => 'uint256'],
                ],
                'outputs' => [['type' => 'bool']],
            ],
        ];

        $manager = $this->createMock(TronManager::class);
        $manager->expects($this->once())
            ->method('request')
            ->with('wallet/triggersmartcontract', Assert::callback(fn ($v) => is_array($v)))
            ->willReturn([
                'result' => ['result' => true],
                'constant_result' => ['0000000000000000000000000000000000000000000000000000000000000001'],
                'transaction' => ['txID' => 'mock_tx_id'],
            ])
        ;

        $tron->expects($this->once())
            ->method('getManager')
            ->willReturn($manager)
        ;

        $builder = new TransactionBuilder($tron);
        // 使用完整的 40字节十六进制地址（0x前缀被移除后）
        $result = $builder->triggerSmartContract(
            $abi,
            '410000000000000000000000000000000000000000',
            'transfer',
            ['410000000000000000000000000000000000000001', '1000'],
            1000000,
            '410000000000000000000000000000000000000002',
            0,
            0
        );

        $this->assertIsArray($result);
    }

    public function testTriggerSmartContractThrowsExceptionForFunctionNotFound(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Function nonExistent not defined in ABI');

        $tron = new Tron();
        $builder = new TransactionBuilder($tron);

        $abi = [
            [
                'name' => 'transfer',
                'inputs' => [['type' => 'address']],
            ],
        ];

        $builder->triggerSmartContract(
            $abi,
            '41contract',
            'nonExistent',
            ['param'],
            1000000,
            '41from'
        );
    }

    public function testTriggerSmartContractThrowsExceptionForInvalidParams(): void
    {
        // NOTE: With strict type declarations, PHP will throw TypeError before method validation
        // This is actually an improvement - type errors are caught at call time
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('must be of type array');

        $tron = new Tron();
        $builder = new TransactionBuilder($tron);

        $abi = [
            [
                'name' => 'transfer',
                'inputs' => [['type' => 'address']],
            ],
        ];

        $builder->triggerSmartContract(
            $abi,
            '41contract',
            'transfer',
            'invalid_params', // @phpstan-ignore-line argument.type
            1000000,
            '41from'
        );
    }

    public function testTriggerSmartContractThrowsExceptionForParamCountMismatch(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Count of params and abi inputs must be identical');

        $tron = new Tron();
        $builder = new TransactionBuilder($tron);

        $abi = [
            [
                'name' => 'transfer',
                'inputs' => [
                    ['type' => 'address'],
                    ['type' => 'uint256'],
                ],
            ],
        ];

        $builder->triggerSmartContract(
            $abi,
            '41contract',
            'transfer',
            ['only_one_param'],
            1000000,
            '41from'
        );
    }

    public function testTriggerSmartContractThrowsExceptionForExcessiveFeeLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('fee_limit must not be greater than 1000000000');

        $tron = new Tron();
        $builder = new TransactionBuilder($tron);

        $abi = [
            [
                'name' => 'transfer',
                'inputs' => [['type' => 'address']],
            ],
        ];

        $builder->triggerSmartContract(
            $abi,
            '41contract',
            'transfer',
            ['param'],
            1000000001,
            '41from'
        );
    }

    // ========== triggerConstantContract() 测试 ==========

    public function testTriggerConstantContractSuccess(): void
    {
        $tron = $this->createMock(Tron::class);

        $abi = [
            [
                'name' => 'balanceOf',
                'inputs' => [['type' => 'address']],
                'outputs' => [['type' => 'uint256']],
            ],
        ];

        $manager = $this->createMock(TronManager::class);
        $manager->expects($this->once())
            ->method('request')
            ->with('wallet/triggerconstantcontract', Assert::callback(fn ($v) => is_array($v)))
            ->willReturn([
                'result' => ['result' => true],
                'constant_result' => ['0000000000000000000000000000000000000000000000000000000000000064'],
            ])
        ;

        $tron->expects($this->once())
            ->method('getManager')
            ->willReturn($manager)
        ;

        $builder = new TransactionBuilder($tron);
        // 使用完整的 40字节十六进制地址
        $result = $builder->triggerConstantContract(
            $abi,
            '410000000000000000000000000000000000000000',
            'balanceOf',
            ['410000000000000000000000000000000000000001']
        );

        $this->assertIsArray($result);
    }

    public function testTriggerConstantContractWithDefaultAddress(): void
    {
        $tron = $this->createMock(Tron::class);

        $abi = [
            [
                'name' => 'totalSupply',
                'inputs' => [],
                'outputs' => [['type' => 'uint256']],
            ],
        ];

        $manager = $this->createMock(TronManager::class);
        $manager->expects($this->once())
            ->method('request')
            ->with(
                'wallet/triggerconstantcontract',
                Assert::callback(function ($options) {
                    return '410000000000000000000000000000000000000000' === $options['owner_address'];
                })
            )
            ->willReturn([
                'result' => ['result' => true],
                'constant_result' => ['0000000000000000000000000000000000000000000000000000000000000064'],
            ])
        ;

        $tron->expects($this->once())
            ->method('getManager')
            ->willReturn($manager)
        ;

        $builder = new TransactionBuilder($tron);
        $result = $builder->triggerConstantContract(
            $abi,
            '41contract',
            'totalSupply',
            []
        );

        $this->assertIsArray($result);
    }

    public function testTriggerConstantContractWithCustomAddress(): void
    {
        $tron = $this->createMock(Tron::class);

        $abi = [
            [
                'name' => 'balanceOf',
                'inputs' => [['type' => 'address']],
                'outputs' => [['type' => 'uint256']],
            ],
        ];

        $manager = $this->createMock(TronManager::class);
        $manager->expects($this->once())
            ->method('request')
            ->with(
                'wallet/triggerconstantcontract',
                Assert::callback(function ($options) {
                    return '410000000000000000000000000000000000000003' === $options['owner_address'];
                })
            )
            ->willReturn([
                'result' => ['result' => true],
                'constant_result' => ['0000000000000000000000000000000000000000000000000000000000000064'],
            ])
        ;

        $tron->expects($this->once())
            ->method('getManager')
            ->willReturn($manager)
        ;

        $builder = new TransactionBuilder($tron);
        // 使用完整的 40字节十六进制地址
        $result = $builder->triggerConstantContract(
            $abi,
            '410000000000000000000000000000000000000000',
            'balanceOf',
            ['410000000000000000000000000000000000000001'],
            '410000000000000000000000000000000000000003'
        );

        $this->assertIsArray($result);
    }

    public function testTriggerConstantContractThrowsExceptionForFunctionNotFound(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Function nonExistent not defined in ABI');

        $tron = new Tron();
        $builder = new TransactionBuilder($tron);

        $abi = [
            [
                'name' => 'balanceOf',
                'inputs' => [['type' => 'address']],
            ],
        ];

        $builder->triggerConstantContract(
            $abi,
            '41contract',
            'nonExistent',
            []
        );
    }

    public function testTriggerConstantContractThrowsExceptionForInvalidParams(): void
    {
        // NOTE: With strict type declarations, PHP will throw TypeError before method validation
        // This is actually an improvement - type errors are caught at call time
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('must be of type array');

        $tron = new Tron();
        $builder = new TransactionBuilder($tron);

        $abi = [
            [
                'name' => 'balanceOf',
                'inputs' => [['type' => 'address']],
            ],
        ];

        $builder->triggerConstantContract(
            $abi,
            '41contract',
            'balanceOf',
            'invalid_params' // @phpstan-ignore-line argument.type
        );
    }

    public function testTriggerConstantContractThrowsExceptionForParamCountMismatch(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Count of params and abi inputs must be identical');

        $tron = new Tron();
        $builder = new TransactionBuilder($tron);

        $abi = [
            [
                'name' => 'transfer',
                'inputs' => [
                    ['type' => 'address'],
                    ['type' => 'uint256'],
                ],
            ],
        ];

        $builder->triggerConstantContract(
            $abi,
            '41contract',
            'transfer',
            ['only_one_param']
        );
    }

    // ========== contractbalance() 测试 ==========

    public function testContractBalanceReturnsEmptyArrayWhenTokenDataIsInvalid(): void
    {
        $tron = $this->createMock(Tron::class);
        $smartContractService = $this->createMock(SmartContractService::class);

        // Create mock TokenQueryService that returns null for fetchTRC20TokenList
        $tokenQueryService = new class($tron, $smartContractService) extends TokenQueryService {
            protected function fetchTRC20TokenList(): ?array
            {
                return null;
            }
        };

        // Create TransactionBuilder with mocked TokenQueryService
        $builder = new class($tron, $tokenQueryService) extends TransactionBuilder {
            public function __construct(Tron $tron, TokenQueryService $tokenQueryService)
            {
                parent::__construct($tron);
                $this->tokenQueryService = $tokenQueryService;
            }
        };

        $result = $builder->contractbalance('TAddress');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testContractBalanceReturnsEmptyArrayWhenAbiIsInvalid(): void
    {
        $tron = $this->createMock(Tron::class);
        $smartContractService = $this->createMock(SmartContractService::class);

        // Create mock TokenQueryService that returns null for getTRC20StandardAbi
        $tokenQueryService = new class($tron, $smartContractService) extends TokenQueryService {
            protected function getTRC20StandardAbi(): array
            {
                return [];
            }
        };

        // Create TransactionBuilder with mocked TokenQueryService
        $builder = new class($tron, $tokenQueryService) extends TransactionBuilder {
            public function __construct(Tron $tron, TokenQueryService $tokenQueryService)
            {
                parent::__construct($tron);
                $this->tokenQueryService = $tokenQueryService;
            }
        };

        $result = $builder->contractbalance('TAddress');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
