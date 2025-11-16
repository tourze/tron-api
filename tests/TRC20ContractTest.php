<?php

namespace Tourze\TronAPI\Tests;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Tourze\TronAPI\Exception\TRC20Exception;
use Tourze\TronAPI\TransactionBuilder;
use Tourze\TronAPI\TRC20Contract;
use Tourze\TronAPI\Tron;

/**
 * @internal
 */
#[CoversClass(TRC20Contract::class)]
class TRC20ContractTest extends TestCase
{
    private function createMockContract(array $mockData = []): TRC20Contract
    {
        $tron = $this->createMock(Tron::class);
        $contract = new TRC20Contract($tron, 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t');

        // 通过反射注入模拟数据
        $reflection = new \ReflectionClass($contract);
        foreach ($mockData as $property => $value) {
            $prop = $reflection->getProperty($property);
            $prop->setAccessible(true);
            $prop->setValue($contract, $value);
        }

        return $contract;
    }

    public function testCanBeInstantiated(): void
    {
        $tron = new Tron();
        $contract = new TRC20Contract($tron, 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t');
        $this->assertInstanceOf(TRC20Contract::class, $contract);
    }

    public function testConstants(): void
    {
        $this->assertSame(1000000, TRC20Contract::TRX_TO_SUN);
    }

    public function testSetFeeLimit(): void
    {
        $tron = new Tron();
        $contract = new TRC20Contract($tron, 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t');
        $result = $contract->setFeeLimit(100);
        $this->assertInstanceOf(TRC20Contract::class, $result);
    }

    public function testCleanStr(): void
    {
        $contract = $this->createMockContract();

        // 测试清理字符串功能
        $this->assertSame('USDT', $contract->cleanStr('USDT'));
        $this->assertSame('USDT', $contract->cleanStr("USDT\0\0\0"));
        $this->assertSame('TRC-20', $contract->cleanStr('TRC-20'));
        $this->assertSame('Token1.0', $contract->cleanStr('Token1.0'));
        $this->assertSame('Test_Token', $contract->cleanStr('Test_Token'));
        $this->assertSame('', $contract->cleanStr('   '));
        $this->assertSame('ABC123', $contract->cleanStr('A@B#C$1%2^3'));
    }

    public function testClearCached(): void
    {
        $contract = $this->createMockContract([
            '_name' => 'Tether USD',
            '_symbol' => 'USDT',
            '_decimals' => 6,
            '_totalSupply' => '1000000000000',
        ]);

        // 验证缓存已设置
        $reflection = new \ReflectionClass($contract);
        $nameProp = $reflection->getProperty('_name');
        $nameProp->setAccessible(true);
        $this->assertSame('Tether USD', $nameProp->getValue($contract));

        // 清除缓存
        $contract->clearCached();

        // 验证缓存已清除
        $this->assertNull($nameProp->getValue($contract));
        $symbolProp = $reflection->getProperty('_symbol');
        $symbolProp->setAccessible(true);
        $this->assertNull($symbolProp->getValue($contract));
        $decimalsProp = $reflection->getProperty('_decimals');
        $decimalsProp->setAccessible(true);
        $this->assertNull($decimalsProp->getValue($contract));
        $totalSupplyProp = $reflection->getProperty('_totalSupply');
        $totalSupplyProp->setAccessible(true);
        $this->assertNull($totalSupplyProp->getValue($contract));
    }

    public function testNameReturnsCachedValue(): void
    {
        $contract = $this->createMockContract(['_name' => 'Tether USD']);
        $this->assertSame('Tether USD', $contract->name());
    }

    public function testSymbolReturnsCachedValue(): void
    {
        $contract = $this->createMockContract(['_symbol' => 'USDT']);
        $this->assertSame('USDT', $contract->symbol());
    }

    public function testDecimalsReturnsCachedValue(): void
    {
        $contract = $this->createMockContract(['_decimals' => 6]);
        $this->assertSame(6, $contract->decimals());
    }

    public function testTotalSupplyReturnsCachedValue(): void
    {
        $contract = $this->createMockContract([
            '_totalSupply' => '1000000000000',
            '_decimals' => 6,
        ]);

        // 测试未缩放值
        $this->assertSame('1000000000000', $contract->totalSupply(false));

        // 测试缩放值（除以 10^6）
        $scaled = $contract->totalSupply(true);
        $this->assertIsString($scaled);
        $this->assertStringContainsString('1000000', $scaled);
    }

    public function testArrayReturnsContractInfo(): void
    {
        $contract = $this->createMockContract([
            '_name' => 'Tether USD',
            '_symbol' => 'USDT',
            '_decimals' => 6,
            '_totalSupply' => '1000000000000',
        ]);

        $result = $contract->array();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('symbol', $result);
        $this->assertArrayHasKey('decimals', $result);
        $this->assertArrayHasKey('totalSupply', $result);

        $this->assertSame('Tether USD', $result['name']);
        $this->assertSame('USDT', $result['symbol']);
        $this->assertSame(6, $result['decimals']);
        $this->assertIsString($result['totalSupply']);
    }

    public function testDebugInfoUsesArray(): void
    {
        $contract = $this->createMockContract([
            '_name' => 'Tether USD',
            '_symbol' => 'USDT',
            '_decimals' => 6,
            '_totalSupply' => '1000000000000',
        ]);

        $debugInfo = $contract->__debugInfo();

        $this->assertIsArray($debugInfo);
        $this->assertArrayHasKey('name', $debugInfo);
        $this->assertArrayHasKey('symbol', $debugInfo);
        $this->assertArrayHasKey('decimals', $debugInfo);
        $this->assertArrayHasKey('totalSupply', $debugInfo);
    }

    public function testBalanceOfWithDefaultAddress(): void
    {
        // 创建 Mock Tron 实例
        $tron = $this->createMock(Tron::class);
        $tron->address = ['base58' => 'TDefaultAddress123456789', 'hex' => '41default'];

        // 模拟 address2HexString 方法 - 会被调用3次：
        // 1. balanceOf 中转换地址（第262行）
        // 2. trigger 中转换owner地址（第385行）
        // 3. trigger 中转换合约地址（第388行）
        $tron->expects($this->exactly(3))
            ->method('address2HexString')
            ->willReturnCallback(function ($addr) {
                if ('TDefaultAddress123456789' === $addr) {
                    return '0000000000000000000000000000000000000000000000000000000041default';
                }
                if ('TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t' === $addr) {
                    return '41contract';
                }

                return '41unknown';
            })
        ;

        // 创建一个匿名类来模拟 BigInteger
        $mockBalance = new class {
            public function toString(): string
            {
                return '1000000000';
            }
        };

        // 创建一个模拟的 TransactionBuilder
        $txBuilder = $this->createMock(TransactionBuilder::class);
        $txBuilder->expects($this->once())
            ->method('triggerConstantContract')
            ->willReturn([$mockBalance])
        ;

        $tron->expects($this->once())
            ->method('getTransactionBuilder')
            ->willReturn($txBuilder)
        ;

        // 创建合约实例
        $contract = new TRC20Contract($tron, 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t');

        // 注入缓存的 decimals
        $reflection = new \ReflectionClass($contract);
        $decimalsProp = $reflection->getProperty('_decimals');
        $decimalsProp->setAccessible(true);
        $decimalsProp->setValue($contract, 6);

        // 调用 balanceOf，应该使用默认地址
        $balance = $contract->balanceOf();

        // 验证余额被正确缩放（1000000000 / 10^6 = 1000）
        $this->assertIsString($balance);
        $this->assertStringContainsString('1000', $balance);
    }

    public function testBalanceOfWithCustomAddress(): void
    {
        // 创建 Mock Tron 实例
        $tron = $this->createMock(Tron::class);
        $tron->address = ['base58' => 'TDefaultAddress123456789', 'hex' => '41default'];

        // 模拟 address2HexString 方法 - 会被调用3次：
        // 1. balanceOf 中转换地址（第262行）
        // 2. trigger 中转换owner地址（第385行）
        // 3. trigger 中转换合约地址（第388行）
        $tron->expects($this->exactly(3))
            ->method('address2HexString')
            ->willReturnCallback(function ($addr) {
                if ('TCustomAddress987654321' === $addr) {
                    return '0000000000000000000000000000000000000000000000000000000041custom';
                }
                if ('TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t' === $addr) {
                    return '41contract';
                }

                return '41unknown';
            })
        ;

        // 创建一个匿名类来模拟 BigInteger
        $mockBalance = new class {
            public function toString(): string
            {
                return '5000000000';
            }
        };

        // 创建一个模拟的 TransactionBuilder
        $txBuilder = $this->createMock(TransactionBuilder::class);
        $txBuilder->expects($this->once())
            ->method('triggerConstantContract')
            ->willReturn([$mockBalance])
        ;

        $tron->expects($this->once())
            ->method('getTransactionBuilder')
            ->willReturn($txBuilder)
        ;

        // 创建合约实例
        $contract = new TRC20Contract($tron, 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t');

        // 注入缓存的 decimals
        $reflection = new \ReflectionClass($contract);
        $decimalsProp = $reflection->getProperty('_decimals');
        $decimalsProp->setAccessible(true);
        $decimalsProp->setValue($contract, 6);

        // 调用 balanceOf，使用自定义地址
        $balance = $contract->balanceOf('TCustomAddress987654321');

        // 验证余额被正确缩放（5000000000 / 10^6 = 5000）
        $this->assertIsString($balance);
        $this->assertStringContainsString('5000', $balance);
    }

    public function testBalanceOfThrowsExceptionOnInvalidBalance(): void
    {
        // 测试当余额返回无效值时抛出异常
        $this->expectException(TRC20Exception::class);
        $this->expectExceptionMessage('Failed to retrieve TRC20 token balance');

        // 创建 Mock Tron 实例
        $tron = $this->createMock(Tron::class);
        $tron->address = ['base58' => 'TAddress123', 'hex' => '41addr'];

        // 会被调用3次
        $tron->expects($this->exactly(3))
            ->method('address2HexString')
            ->willReturnCallback(function ($addr) {
                if ('TAddress123' === $addr) {
                    return '0000000000000000000000000000000000000000000000000000000041addr';
                }
                if ('TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t' === $addr) {
                    return '41contract';
                }

                return '41unknown';
            })
        ;

        // 创建一个匿名类来模拟返回无效余额
        $mockInvalidBalance = new class {
            public function toString(): string
            {
                return 'not-a-number'; // 返回非数字字符串
            }
        };

        // 创建一个模拟的 TransactionBuilder
        $txBuilder = $this->createMock(TransactionBuilder::class);
        $txBuilder->expects($this->once())
            ->method('triggerConstantContract')
            ->willReturn([$mockInvalidBalance]) // 返回无效数据
        ;

        $tron->expects($this->once())
            ->method('getTransactionBuilder')
            ->willReturn($txBuilder)
        ;

        $contract = new TRC20Contract($tron, 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t');

        // 注入缓存的 decimals
        $reflection = new \ReflectionClass($contract);
        $decimalsProp = $reflection->getProperty('_decimals');
        $decimalsProp->setAccessible(true);
        $decimalsProp->setValue($contract, 6);

        // 这应该抛出异常
        $contract->balanceOf('TAddress123');
    }

    public function testTransferWithDefaultFromAddress(): void
    {
        // 创建 Mock Tron 实例
        $tron = $this->createMock(Tron::class);
        $tron->address = ['base58' => 'TFromAddress123', 'hex' => '41from'];

        // 模拟 address2HexString 方法 - 会被调用3次：
        // 1. transfer 中转换to地址（第300行）
        // 2. transfer 中转换合约地址（第304行）
        // 3. transfer 中转换from地址（第308行）
        $tron->expects($this->exactly(3))
            ->method('address2HexString')
            ->willReturnCallback(function ($addr) {
                if ('TToAddress456' === $addr) {
                    return '41to';
                }
                if ('TFromAddress123' === $addr) {
                    return '41from';
                }
                if ('TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t' === $addr) {
                    return '41contract';
                }

                return '41unknown';
            })
        ;

        // 创建一个模拟的 TransactionBuilder
        $txBuilder = $this->createMock(TransactionBuilder::class);
        $mockTransaction = ['txID' => 'mock_tx_id', 'raw_data' => []];
        $txBuilder->expects($this->once())
            ->method('triggerSmartContract')
            ->willReturn($mockTransaction)
        ;

        $tron->expects($this->once())
            ->method('getTransactionBuilder')
            ->willReturn($txBuilder)
        ;

        // 模拟签名交易
        $signedTx = ['txID' => 'mock_tx_id', 'signature' => ['sig1']];
        $tron->expects($this->once())
            ->method('signTransaction')
            ->with($mockTransaction)
            ->willReturn($signedTx)
        ;

        // 模拟发送交易
        $response = ['result' => true];
        $tron->expects($this->once())
            ->method('sendRawTransaction')
            ->with($signedTx)
            ->willReturn($response)
        ;

        $contract = new TRC20Contract($tron, 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t');

        // 注入缓存的 decimals
        $reflection = new \ReflectionClass($contract);
        $decimalsProp = $reflection->getProperty('_decimals');
        $decimalsProp->setAccessible(true);
        $decimalsProp->setValue($contract, 6);

        // 执行转账
        $result = $contract->transfer('TToAddress456', '100');

        // 验证返回结果合并了响应和签名交易
        $this->assertIsArray($result);
        $this->assertArrayHasKey('result', $result);
        $this->assertArrayHasKey('signature', $result);
        $this->assertTrue($result['result']);
    }

    public function testTransferWithCustomFromAddress(): void
    {
        // 创建 Mock Tron 实例
        $tron = $this->createMock(Tron::class);
        $tron->address = ['base58' => 'TDefaultAddress', 'hex' => '41default'];

        // 模拟 address2HexString 方法 - 会被调用3次
        $tron->expects($this->exactly(3))
            ->method('address2HexString')
            ->willReturnCallback(function ($addr) {
                if ('TToAddress456' === $addr) {
                    return '41to';
                }
                if ('TCustomFrom789' === $addr) {
                    return '41customfrom';
                }
                if ('TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t' === $addr) {
                    return '41contract';
                }

                return '41unknown';
            })
        ;

        // 创建一个模拟的 TransactionBuilder
        $txBuilder = $this->createMock(TransactionBuilder::class);
        $mockTransaction = ['txID' => 'mock_tx_id', 'raw_data' => []];
        $txBuilder->expects($this->once())
            ->method('triggerSmartContract')
            ->willReturn($mockTransaction)
        ;

        $tron->expects($this->once())
            ->method('getTransactionBuilder')
            ->willReturn($txBuilder)
        ;

        // 模拟签名交易
        $signedTx = ['txID' => 'mock_tx_id', 'signature' => ['sig1']];
        $tron->expects($this->once())
            ->method('signTransaction')
            ->with($mockTransaction)
            ->willReturn($signedTx)
        ;

        // 模拟发送交易
        $response = ['result' => true];
        $tron->expects($this->once())
            ->method('sendRawTransaction')
            ->with($signedTx)
            ->willReturn($response)
        ;

        $contract = new TRC20Contract($tron, 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t');

        // 注入缓存的 decimals
        $reflection = new \ReflectionClass($contract);
        $decimalsProp = $reflection->getProperty('_decimals');
        $decimalsProp->setAccessible(true);
        $decimalsProp->setValue($contract, 6);

        // 执行转账，指定 from 地址
        $result = $contract->transfer('TToAddress456', '100', 'TCustomFrom789');

        // 验证返回结果
        $this->assertIsArray($result);
        $this->assertArrayHasKey('result', $result);
        $this->assertTrue($result['result']);
    }

    public function testTransferThrowsExceptionWhenFeeLimitIsZero(): void
    {
        $this->expectException(TRC20Exception::class);
        $this->expectExceptionMessage('fee_limit is required.');

        $tron = $this->createMock(Tron::class);
        $tron->address = ['base58' => 'TFromAddress123', 'hex' => '41from'];

        $contract = new TRC20Contract($tron, 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t');

        // 设置 feeLimit 为 0
        $reflection = new \ReflectionClass($contract);
        $feeLimitProp = $reflection->getProperty('feeLimit');
        $feeLimitProp->setAccessible(true);
        $feeLimitProp->setValue($contract, 0);

        // 注入缓存的 decimals
        $decimalsProp = $reflection->getProperty('_decimals');
        $decimalsProp->setAccessible(true);
        $decimalsProp->setValue($contract, 6);

        // 这应该抛出异常
        $contract->transfer('TToAddress456', '100');
    }

    public function testTransferThrowsExceptionWhenFeeLimitExceedsMaximum(): void
    {
        $this->expectException(TRC20Exception::class);
        $this->expectExceptionMessage('fee_limit must not be greater than 1000 TRX.');

        $tron = $this->createMock(Tron::class);
        $tron->address = ['base58' => 'TFromAddress123', 'hex' => '41from'];

        $contract = new TRC20Contract($tron, 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t');

        // 设置 feeLimit 超过 1000
        $reflection = new \ReflectionClass($contract);
        $feeLimitProp = $reflection->getProperty('feeLimit');
        $feeLimitProp->setAccessible(true);
        $feeLimitProp->setValue($contract, 1001);

        // 注入缓存的 decimals
        $decimalsProp = $reflection->getProperty('_decimals');
        $decimalsProp->setAccessible(true);
        $decimalsProp->setValue($contract, 6);

        // 这应该抛出异常
        $contract->transfer('TToAddress456', '100');
    }

    public function testTransferWithDifferentDecimals(): void
    {
        // 测试不同的 decimals 值（例如 USDC 使用 6，其他可能使用 18）
        $tron = $this->createMock(Tron::class);
        $tron->address = ['base58' => 'TFromAddress123', 'hex' => '41from'];

        // 会被调用3次
        $tron->expects($this->exactly(3))
            ->method('address2HexString')
            ->willReturnCallback(function ($addr) {
                if ('TToAddress456' === $addr) {
                    return '41to';
                }
                if ('TFromAddress123' === $addr) {
                    return '41from';
                }
                if ('TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t' === $addr) {
                    return '41contract';
                }

                return '41unknown';
            })
        ;

        $txBuilder = $this->createMock(TransactionBuilder::class);
        $mockTransaction = ['txID' => 'mock_tx_id', 'raw_data' => []];

        // 验证 triggerSmartContract 被调用时，amount 参数被正确计算
        // 对于 decimals=18，100 应该变成 100 * 10^18
        $txBuilder->expects($this->once())
            ->method('triggerSmartContract')
            ->with(
                Assert::anything(),
                Assert::anything(),
                'transfer',
                Assert::callback(function ($params) {
                    // 验证 amount 被正确计算为 100 * 10^18 = 100000000000000000000
                    return isset($params['1']) && '100000000000000000000' === $params['1'];
                }),
                Assert::anything(),
                Assert::anything()
            )
            ->willReturn($mockTransaction)
        ;

        $tron->expects($this->once())
            ->method('getTransactionBuilder')
            ->willReturn($txBuilder)
        ;

        $signedTx = ['txID' => 'mock_tx_id', 'signature' => ['sig1']];
        $tron->expects($this->once())
            ->method('signTransaction')
            ->willReturn($signedTx)
        ;

        $response = ['result' => true];
        $tron->expects($this->once())
            ->method('sendRawTransaction')
            ->willReturn($response)
        ;

        $contract = new TRC20Contract($tron, 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t');

        // 注入 decimals=18（例如 ETH 标准）
        $reflection = new \ReflectionClass($contract);
        $decimalsProp = $reflection->getProperty('_decimals');
        $decimalsProp->setAccessible(true);
        $decimalsProp->setValue($contract, 18);

        // 执行转账
        $result = $contract->transfer('TToAddress456', '100');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('result', $result);
    }
}
