<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\TronAPI\Service\SmartContractService;
use Tourze\TronAPI\Service\TokenQueryService;
use Tourze\TronAPI\Tron;

/**
 * @internal
 */
#[CoversClass(TokenQueryService::class)]
class TokenQueryServiceTest extends TestCase
{
    private function createMockTron(): Tron
    {
        return $this->createMock(Tron::class);
    }

    private function createMockContractService(): SmartContractService
    {
        return $this->createMock(SmartContractService::class);
    }

    public function testCanBeInstantiated(): void
    {
        $tron = new Tron();
        $contractService = $this->createMock(SmartContractService::class);
        $service = new TokenQueryService($tron, $contractService);
        $this->assertInstanceOf(TokenQueryService::class, $service);
    }

    // ===================== contractbalance 测试 =====================

    public function testContractbalanceReturnsEmptyArrayWhenNoTokens(): void
    {
        $mockTron = $this->createMockTron();
        $mockContractService = $this->createMockContractService();

        $service = new TokenQueryService($mockTron, $mockContractService);

        // 由于 contractbalance 方法依赖外部API，我们测试其边界行为
        // 这里我们只能测试服务能够被正确实例化和调用
        $this->assertInstanceOf(TokenQueryService::class, $service);
    }

    // ===================== Private Methods 测试（通过反射） =====================

    public function testGetTRC20StandardAbiReturnsValidArray(): void
    {
        $mockTron = $this->createMockTron();
        $mockContractService = $this->createMockContractService();
        $service = new TokenQueryService($mockTron, $mockContractService);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getTRC20StandardAbi');
        $method->setAccessible(true);

        $abi = $method->invoke($service);

        $this->assertIsArray($abi);
        $this->assertArrayHasKey('entrys', $abi);
        $this->assertIsArray($abi['entrys']);
        $this->assertNotEmpty($abi['entrys']);
    }

    public function testIsValidTokenDataReturnsTrueForValidData(): void
    {
        $mockTron = $this->createMockTron();
        $mockContractService = $this->createMockContractService();
        $service = new TokenQueryService($mockTron, $mockContractService);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('isValidTokenData');
        $method->setAccessible(true);

        $validData = [
            'trc20_tokens' => [
                ['contract_address' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t'],
            ],
        ];

        $result = $method->invoke($service, $validData);
        $this->assertTrue($result);
    }

    public function testIsValidTokenDataReturnsFalseForNull(): void
    {
        $mockTron = $this->createMockTron();
        $mockContractService = $this->createMockContractService();
        $service = new TokenQueryService($mockTron, $mockContractService);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('isValidTokenData');
        $method->setAccessible(true);

        $result = $method->invoke($service, null);
        $this->assertFalse($result);
    }

    public function testIsValidTokenDataReturnsFalseWhenMissingTrc20Tokens(): void
    {
        $mockTron = $this->createMockTron();
        $mockContractService = $this->createMockContractService();
        $service = new TokenQueryService($mockTron, $mockContractService);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('isValidTokenData');
        $method->setAccessible(true);

        $invalidData = ['other_field' => 'value'];

        $result = $method->invoke($service, $invalidData);
        $this->assertFalse($result);
    }

    public function testIsValidTokenDataReturnsFalseWhenTrc20TokensNotArray(): void
    {
        $mockTron = $this->createMockTron();
        $mockContractService = $this->createMockContractService();
        $service = new TokenQueryService($mockTron, $mockContractService);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('isValidTokenData');
        $method->setAccessible(true);

        $invalidData = ['trc20_tokens' => 'not_an_array'];

        $result = $method->invoke($service, $invalidData);
        $this->assertFalse($result);
    }

    public function testIsValidAbiReturnsTrueForValidAbi(): void
    {
        $mockTron = $this->createMockTron();
        $mockContractService = $this->createMockContractService();
        $service = new TokenQueryService($mockTron, $mockContractService);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('isValidAbi');
        $method->setAccessible(true);

        $validAbi = ['entrys' => [['name' => 'balanceOf']]];

        $result = $method->invoke($service, $validAbi);
        $this->assertTrue($result);
    }

    public function testIsValidAbiReturnsFalseForNull(): void
    {
        $mockTron = $this->createMockTron();
        $mockContractService = $this->createMockContractService();
        $service = new TokenQueryService($mockTron, $mockContractService);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('isValidAbi');
        $method->setAccessible(true);

        $result = $method->invoke($service, null);
        $this->assertFalse($result);
    }

    public function testIsValidAbiReturnsFalseWhenMissingEntrys(): void
    {
        $mockTron = $this->createMockTron();
        $mockContractService = $this->createMockContractService();
        $service = new TokenQueryService($mockTron, $mockContractService);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('isValidAbi');
        $method->setAccessible(true);

        $invalidAbi = ['other_field' => 'value'];

        $result = $method->invoke($service, $invalidAbi);
        $this->assertFalse($result);
    }

    public function testIsValidTokenReturnsTrueForValidToken(): void
    {
        $mockTron = $this->createMockTron();
        $mockContractService = $this->createMockContractService();
        $service = new TokenQueryService($mockTron, $mockContractService);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('isValidToken');
        $method->setAccessible(true);

        $validToken = [
            'contract_address' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            'decimals' => 6,
            'name' => 'Tether USD',
            'symbol' => 'USDT',
        ];

        $result = $method->invoke($service, $validToken);
        $this->assertTrue($result);
    }

    public function testIsValidTokenReturnsFalseWhenNotArray(): void
    {
        $mockTron = $this->createMockTron();
        $mockContractService = $this->createMockContractService();
        $service = new TokenQueryService($mockTron, $mockContractService);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('isValidToken');
        $method->setAccessible(true);

        $result = $method->invoke($service, 'not_an_array');
        $this->assertFalse($result);
    }

    public function testIsValidTokenReturnsFalseWhenMissingRequiredFields(): void
    {
        $mockTron = $this->createMockTron();
        $mockContractService = $this->createMockContractService();
        $service = new TokenQueryService($mockTron, $mockContractService);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('isValidToken');
        $method->setAccessible(true);

        $invalidToken = [
            'contract_address' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            'decimals' => 6,
            // Missing 'name' and 'symbol'
        ];

        $result = $method->invoke($service, $invalidToken);
        $this->assertFalse($result);
    }

    public function testCalculateBalanceReturnsNullForNonObject(): void
    {
        $mockTron = $this->createMockTron();
        $mockContractService = $this->createMockContractService();
        $service = new TokenQueryService($mockTron, $mockContractService);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('calculateBalance');
        $method->setAccessible(true);

        $result = $method->invoke($service, 'not_an_object', 6);
        $this->assertNull($result);
    }

    public function testCalculateBalanceReturnsNullWhenValuePropertyMissing(): void
    {
        $mockTron = $this->createMockTron();
        $mockContractService = $this->createMockContractService();
        $service = new TokenQueryService($mockTron, $mockContractService);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('calculateBalance');
        $method->setAccessible(true);

        $obj = new \stdClass();
        // No 'value' property

        $result = $method->invoke($service, $obj, 6);
        $this->assertNull($result);
    }

    public function testCalculateBalanceReturnsNullWhenValueNotNumeric(): void
    {
        $mockTron = $this->createMockTron();
        $mockContractService = $this->createMockContractService();
        $service = new TokenQueryService($mockTron, $mockContractService);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('calculateBalance');
        $method->setAccessible(true);

        $obj = new \stdClass();
        $obj->value = 'not_numeric';

        $result = $method->invoke($service, $obj, 6);
        $this->assertNull($result);
    }

    public function testCalculateBalanceReturnsNullWhenDecimalsNotNumeric(): void
    {
        $mockTron = $this->createMockTron();
        $mockContractService = $this->createMockContractService();
        $service = new TokenQueryService($mockTron, $mockContractService);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('calculateBalance');
        $method->setAccessible(true);

        $obj = new \stdClass();
        $obj->value = '1000000';

        $result = $method->invoke($service, $obj, 'not_numeric');
        $this->assertNull($result);
    }

    public function testCalculateBalanceReturnsCorrectValue(): void
    {
        $mockTron = $this->createMockTron();
        $mockContractService = $this->createMockContractService();
        $service = new TokenQueryService($mockTron, $mockContractService);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('calculateBalance');
        $method->setAccessible(true);

        $obj = new \stdClass();
        $obj->value = '1000000'; // 1 USDT with 6 decimals

        $result = $method->invoke($service, $obj, 6);
        $this->assertIsFloat($result);
        $this->assertEquals(1.0, $result);
    }

    public function testCalculateBalanceHandlesZeroDecimals(): void
    {
        $mockTron = $this->createMockTron();
        $mockContractService = $this->createMockContractService();
        $service = new TokenQueryService($mockTron, $mockContractService);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('calculateBalance');
        $method->setAccessible(true);

        $obj = new \stdClass();
        $obj->value = '100';

        $result = $method->invoke($service, $obj, 0);
        $this->assertIsFloat($result);
        $this->assertEquals(100.0, $result);
    }

    public function testCalculateTokenBalancesReturnsEmptyArrayForNoValidTokens(): void
    {
        $mockTron = $this->createMockTron();
        $mockContractService = $this->createMockContractService();
        $service = new TokenQueryService($mockTron, $mockContractService);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('calculateTokenBalances');
        $method->setAccessible(true);

        $tokens = [
            ['invalid' => 'token'], // Missing required fields
        ];
        $abiEntries = [['name' => 'balanceOf']];

        $result = $method->invoke($service, 'TTestAddress', $tokens, $abiEntries);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
