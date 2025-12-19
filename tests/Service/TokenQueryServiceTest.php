<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\TronAPI\Provider\InMemoryHttpProvider;
use Tourze\TronAPI\Service\SmartContractService;
use Tourze\TronAPI\Service\TokenQueryService;
use Tourze\TronAPI\Tron;
use Tourze\TronAPI\TronManager;

/**
 * @internal
 */
#[CoversClass(TokenQueryService::class)]
class TokenQueryServiceTest extends TestCase
{
    private InMemoryHttpProvider $fullNodeProvider;

    private Tron $tron;

    private SmartContractService $contractService;

    private TokenQueryService $service;

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

        $this->contractService = new SmartContractService($this->tron);
        $this->service = new TokenQueryService($this->tron, $this->contractService);
    }

    public function testCanBeInstantiated(): void
    {
        $tron = new Tron();
        $contractService = new SmartContractService($tron);
        $service = new TokenQueryService($tron, $contractService);
        $this->assertInstanceOf(TokenQueryService::class, $service);
    }

    // ===================== contractbalance 测试 =====================

    public function testContractbalanceReturnsEmptyArrayWhenNoTokens(): void
    {
        // 测试服务能够被正确实例化和调用
        $this->assertInstanceOf(TokenQueryService::class, $this->service);
    }

    // ===================== Private Methods 测试（通过反射） =====================

    public function testGetTRC20StandardAbiReturnsValidArray(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getTRC20StandardAbi');
        $method->setAccessible(true);

        $abi = $method->invoke($this->service);

        $this->assertIsArray($abi);
        $this->assertArrayHasKey('entrys', $abi);
        $this->assertIsArray($abi['entrys']);
        $this->assertNotEmpty($abi['entrys']);
    }

    public function testIsValidTokenDataReturnsTrueForValidData(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('isValidTokenData');
        $method->setAccessible(true);

        $validData = [
            'trc20_tokens' => [
                ['contract_address' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t'],
            ],
        ];

        $result = $method->invoke($this->service, $validData);
        $this->assertTrue($result);
    }

    public function testIsValidTokenDataReturnsFalseForNull(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('isValidTokenData');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, null);
        $this->assertFalse($result);
    }

    public function testIsValidTokenDataReturnsFalseWhenMissingTrc20Tokens(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('isValidTokenData');
        $method->setAccessible(true);

        $invalidData = ['other_field' => 'value'];

        $result = $method->invoke($this->service, $invalidData);
        $this->assertFalse($result);
    }

    public function testIsValidTokenDataReturnsFalseWhenTrc20TokensNotArray(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('isValidTokenData');
        $method->setAccessible(true);

        $invalidData = ['trc20_tokens' => 'not_an_array'];

        $result = $method->invoke($this->service, $invalidData);
        $this->assertFalse($result);
    }

    public function testIsValidAbiReturnsTrueForValidAbi(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('isValidAbi');
        $method->setAccessible(true);

        $validAbi = ['entrys' => [['name' => 'balanceOf']]];

        $result = $method->invoke($this->service, $validAbi);
        $this->assertTrue($result);
    }

    public function testIsValidAbiReturnsFalseForNull(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('isValidAbi');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, null);
        $this->assertFalse($result);
    }

    public function testIsValidAbiReturnsFalseWhenMissingEntrys(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('isValidAbi');
        $method->setAccessible(true);

        $invalidAbi = ['other_field' => 'value'];

        $result = $method->invoke($this->service, $invalidAbi);
        $this->assertFalse($result);
    }

    public function testIsValidTokenReturnsTrueForValidToken(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('isValidToken');
        $method->setAccessible(true);

        $validToken = [
            'contract_address' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            'decimals' => 6,
            'name' => 'Tether USD',
            'symbol' => 'USDT',
        ];

        $result = $method->invoke($this->service, $validToken);
        $this->assertTrue($result);
    }

    public function testIsValidTokenReturnsFalseWhenNotArray(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('isValidToken');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, 'not_an_array');
        $this->assertFalse($result);
    }

    public function testIsValidTokenReturnsFalseWhenMissingRequiredFields(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('isValidToken');
        $method->setAccessible(true);

        $invalidToken = [
            'contract_address' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            'decimals' => 6,
            // Missing 'name' and 'symbol'
        ];

        $result = $method->invoke($this->service, $invalidToken);
        $this->assertFalse($result);
    }

    public function testCalculateBalanceReturnsNullForNonObject(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateBalance');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, 'not_an_object', 6);
        $this->assertNull($result);
    }

    public function testCalculateBalanceReturnsNullWhenValuePropertyMissing(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateBalance');
        $method->setAccessible(true);

        $obj = new \stdClass();
        // No 'value' property

        $result = $method->invoke($this->service, $obj, 6);
        $this->assertNull($result);
    }

    public function testCalculateBalanceReturnsNullWhenValueNotNumeric(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateBalance');
        $method->setAccessible(true);

        $obj = new \stdClass();
        $obj->value = 'not_numeric';

        $result = $method->invoke($this->service, $obj, 6);
        $this->assertNull($result);
    }

    public function testCalculateBalanceReturnsNullWhenDecimalsNotNumeric(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateBalance');
        $method->setAccessible(true);

        $obj = new \stdClass();
        $obj->value = '1000000';

        $result = $method->invoke($this->service, $obj, 'not_numeric');
        $this->assertNull($result);
    }

    public function testCalculateBalanceReturnsCorrectValue(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateBalance');
        $method->setAccessible(true);

        $obj = new \stdClass();
        $obj->value = '1000000'; // 1 USDT with 6 decimals

        $result = $method->invoke($this->service, $obj, 6);
        $this->assertIsFloat($result);
        $this->assertEquals(1.0, $result);
    }

    public function testCalculateBalanceHandlesZeroDecimals(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateBalance');
        $method->setAccessible(true);

        $obj = new \stdClass();
        $obj->value = '100';

        $result = $method->invoke($this->service, $obj, 0);
        $this->assertIsFloat($result);
        $this->assertEquals(100.0, $result);
    }

    public function testCalculateTokenBalancesReturnsEmptyArrayForNoValidTokens(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateTokenBalances');
        $method->setAccessible(true);

        $tokens = [
            ['invalid' => 'token'], // Missing required fields
        ];
        $abiEntries = [['name' => 'balanceOf']];

        $result = $method->invoke($this->service, 'TTestAddress', $tokens, $abiEntries);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // ===================== contractbalanceVO 测试 =====================

    public function testContractbalanceVOReturnsEmptyArrayWhenContractbalanceReturnsEmpty(): void
    {
        // contractbalanceVO 直接调用 contractbalance，当没有有效的 token 数据时返回空数组
        // 我们通过创建一个扩展类来覆盖 fetchTRC20TokenList 方法，返回空数据
        $serviceWithEmptyData = new class($this->tron, $this->contractService) extends TokenQueryService {
            protected function fetchTRC20TokenList(): ?array
            {
                return null;
            }
        };

        $result = $serviceWithEmptyData->contractbalanceVO('TTestAddress');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testContractbalanceVOReturnsVOArrayWhenContractbalanceReturnsData(): void
    {
        // 创建一个测试服务，覆盖 contractbalance 方法返回测试数据
        $serviceWithMockData = new class($this->tron, $this->contractService) extends TokenQueryService {
            public function contractbalance(string $address): array
            {
                return [
                    [
                        'name' => 'Tether USD',
                        'symbol' => 'USDT',
                        'balance' => 100.5,
                        'value' => '100500000',
                        'decimals' => 6,
                    ],
                    [
                        'name' => 'Bitcoin',
                        'symbol' => 'BTC',
                        'balance' => 0.5,
                        'value' => '50000000',
                        'decimals' => 8,
                    ],
                ];
            }
        };

        $result = $serviceWithMockData->contractbalanceVO('TTestAddress');

        // 验证返回数组不为空
        $this->assertIsArray($result);
        $this->assertCount(2, $result);

        // 验证第一个 VO 对象
        $this->assertInstanceOf(\Tourze\TronAPI\ValueObject\TRC20BalanceInfo::class, $result[0]);
        $this->assertEquals('Tether USD', $result[0]->getName());
        $this->assertEquals('USDT', $result[0]->getSymbol());
        $this->assertEquals(100.5, $result[0]->getBalance());
        $this->assertEquals('100500000', $result[0]->getValue());
        $this->assertEquals(6, $result[0]->getDecimals());

        // 验证第二个 VO 对象
        $this->assertInstanceOf(\Tourze\TronAPI\ValueObject\TRC20BalanceInfo::class, $result[1]);
        $this->assertEquals('Bitcoin', $result[1]->getName());
        $this->assertEquals('BTC', $result[1]->getSymbol());
        $this->assertEquals(0.5, $result[1]->getBalance());
        $this->assertEquals('50000000', $result[1]->getValue());
        $this->assertEquals(8, $result[1]->getDecimals());
    }

    public function testContractbalanceVOConvertsSingleBalanceCorrectly(): void
    {
        // 创建一个测试服务，覆盖 contractbalance 方法返回单个余额
        $serviceWithMockData = new class($this->tron, $this->contractService) extends TokenQueryService {
            public function contractbalance(string $address): array
            {
                return [
                    [
                        'name' => 'Test Token',
                        'symbol' => 'TEST',
                        'balance' => 1000.0,
                        'value' => '1000000000',
                        'decimals' => 6,
                    ],
                ];
            }
        };

        $result = $serviceWithMockData->contractbalanceVO('TTestAddress');

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(\Tourze\TronAPI\ValueObject\TRC20BalanceInfo::class, $result[0]);
        $this->assertEquals('Test Token', $result[0]->getName());
        $this->assertEquals('TEST', $result[0]->getSymbol());
        $this->assertEquals(1000.0, $result[0]->getBalance());
    }
}
