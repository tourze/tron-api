<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Tests\Service;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\TronAPI\Exception\InvalidArgumentException;
use Tourze\TronAPI\Exception\RuntimeException;
use Tourze\TronAPI\Service\SmartContractService;
use Tourze\TronAPI\Tron;
use Tourze\TronAPI\TronManager;

/**
 * @internal
 */
#[CoversClass(SmartContractService::class)]
class SmartContractServiceTest extends TestCase
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
        $service = new SmartContractService($tron);
        $this->assertInstanceOf(SmartContractService::class, $service);
    }

    public function testTriggerSmartContractThrowsExceptionForInvalidParams(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('must be of type array');

        $mockTron = $this->createMockTron();
        $service = new SmartContractService($mockTron);

        $abi = [
            ['name' => 'transfer', 'inputs' => [['type' => 'address'], ['type' => 'uint256']]],
        ];

        $service->triggerSmartContract(
            $abi,
            '41testcontractaddress',
            'transfer',
            'invalid_params', // Not an array - will trigger TypeError at PHP runtime
            1000000,
            '41testowneraddress'
        );
    }

    public function testTriggerSmartContractThrowsExceptionForMissingFunction(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Function nonExistent not defined in ABI');

        $mockTron = $this->createMockTron();
        $service = new SmartContractService($mockTron);

        $abi = [
            ['name' => 'transfer', 'inputs' => [['type' => 'address'], ['type' => 'uint256']]],
        ];

        $service->triggerSmartContract(
            $abi,
            '41testcontractaddress',
            'nonExistent', // Function not in ABI
            ['0x123', 100],
            1000000,
            '41testowneraddress'
        );
    }

    public function testTriggerSmartContractThrowsExceptionForParamCountMismatch(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Count of params and abi inputs must be identical');

        $mockTron = $this->createMockTron();
        $service = new SmartContractService($mockTron);

        $abi = [
            ['name' => 'transfer', 'inputs' => [['type' => 'address'], ['type' => 'uint256']]],
        ];

        $service->triggerSmartContract(
            $abi,
            '41testcontractaddress',
            'transfer',
            ['0x123'], // Only 1 param, need 2
            1000000,
            '41testowneraddress'
        );
    }

    public function testTriggerSmartContractThrowsExceptionForExcessiveFeeLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('fee_limit must not be greater than 1000000000');

        $mockTron = $this->createMockTron();
        $service = new SmartContractService($mockTron);

        $abi = [
            ['name' => 'transfer', 'inputs' => [['type' => 'address'], ['type' => 'uint256']]],
        ];

        $service->triggerSmartContract(
            $abi,
            '41testcontractaddress',
            'transfer',
            ['0x123', 100],
            2000000000, // Exceeds max
            '41testowneraddress'
        );
    }

    public function testTriggerConstantContractThrowsExceptionForInvalidParams(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('must be of type array');

        $mockTron = $this->createMockTron();
        $service = new SmartContractService($mockTron);

        $abi = [
            ['name' => 'balanceOf', 'inputs' => [['type' => 'address']]],
        ];

        $service->triggerConstantContract(
            $abi,
            '41testcontractaddress',
            'balanceOf',
            'invalid_params' // Not an array - will trigger TypeError at PHP runtime
        );
    }

    public function testTriggerConstantContractThrowsExceptionForMissingFunction(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Function nonExistent not defined in ABI');

        $mockTron = $this->createMockTron();
        $service = new SmartContractService($mockTron);

        $abi = [
            ['name' => 'balanceOf', 'inputs' => [['type' => 'address']]],
        ];

        $service->triggerConstantContract(
            $abi,
            '41testcontractaddress',
            'nonExistent', // Function not in ABI
            ['0x123']
        );
    }

    public function testTriggerConstantContractThrowsExceptionForParamCountMismatch(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Count of params and abi inputs must be identical');

        $mockTron = $this->createMockTron();
        $service = new SmartContractService($mockTron);

        $abi = [
            ['name' => 'balanceOf', 'inputs' => [['type' => 'address']]],
        ];

        $service->triggerConstantContract(
            $abi,
            '41testcontractaddress',
            'balanceOf',
            [] // Need 1 param
        );
    }

    public function testTriggerSmartContractThrowsExceptionWhenNoResultField(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No result field in response');

        $mockManager = $this->createMockManager();
        $mockTron = $this->createMockTron();
        $mockTron->expects($this->once())
            ->method('getManager')
            ->willReturn($mockManager)
        ;

        $mockManager->expects($this->once())
            ->method('request')
            ->willReturn([]) // No result field
        ;

        $service = new SmartContractService($mockTron);

        $abi = [
            ['name' => 'transfer', 'inputs' => [['type' => 'address'], ['type' => 'uint256']]],
        ];

        $service->triggerSmartContract(
            $abi,
            '41testcontractaddress',
            'transfer',
            ['0x123', 100],
            1000000,
            '41testowneraddress'
        );
    }

    public function testTriggerSmartContractThrowsExceptionOnExecutionFailure(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to execute');

        $mockManager = $this->createMockManager();
        $mockTron = $this->createMockTron();
        $mockTron->expects($this->once())
            ->method('getManager')
            ->willReturn($mockManager)
        ;

        $mockTron->expects($this->once())
            ->method('hexString2Utf8')
            ->with('48656c6c6f')
            ->willReturn('Hello')
        ;

        $mockManager->expects($this->once())
            ->method('request')
            ->willReturn([
                'result' => [
                    'message' => '48656c6c6f', // "Hello" in hex
                ],
            ])
        ;

        $service = new SmartContractService($mockTron);

        $abi = [
            ['name' => 'transfer', 'inputs' => [['type' => 'address'], ['type' => 'uint256']]],
        ];

        $service->triggerSmartContract(
            $abi,
            '41testcontractaddress',
            'transfer',
            ['0x123', 100],
            1000000,
            '41testowneraddress'
        );
    }

    public function testTriggerConstantContractWithEmptyParams(): void
    {
        $mockManager = $this->createMockManager();
        $mockTron = $this->createMockTron();
        $mockTron->expects($this->once())
            ->method('getManager')
            ->willReturn($mockManager)
        ;

        $mockManager->expects($this->once())
            ->method('request')
            ->willReturn([
                'result' => [
                    'result' => true,
                ],
                'transaction' => ['txID' => 'test123'],
            ])
        ;

        $service = new SmartContractService($mockTron);

        $abi = [
            ['name' => 'totalSupply', 'inputs' => []],
        ];

        $result = $service->triggerConstantContract(
            $abi,
            '41testcontractaddress',
            'totalSupply',
            [] // No params needed
        );

        $this->assertIsArray($result);
    }

    public function testTriggerConstantContractWithDefaultAddress(): void
    {
        $mockManager = $this->createMockManager();
        $mockTron = $this->createMockTron();
        $mockTron->expects($this->once())
            ->method('getManager')
            ->willReturn($mockManager)
        ;

        $mockManager->expects($this->once())
            ->method('request')
            ->with(
                'wallet/triggerconstantcontract',
                Assert::callback(function ($params) {
                    return '410000000000000000000000000000000000000000' === $params['owner_address'];
                })
            )
            ->willReturn([
                'result' => [
                    'result' => true,
                ],
                'transaction' => ['txID' => 'test123'],
            ])
        ;

        $service = new SmartContractService($mockTron);

        $abi = [
            ['name' => 'totalSupply', 'inputs' => []],
        ];

        // Use default address
        $result = $service->triggerConstantContract(
            $abi,
            '41testcontractaddress',
            'totalSupply'
        );

        $this->assertIsArray($result);
    }

    public function testTriggerSmartContractBuildsCorrectSignature(): void
    {
        $mockManager = $this->createMockManager();
        $mockTron = $this->createMockTron();
        $mockTron->expects($this->once())
            ->method('getManager')
            ->willReturn($mockManager)
        ;

        $mockManager->expects($this->once())
            ->method('request')
            ->with(
                'wallet/triggersmartcontract',
                Assert::callback(function ($params) {
                    // Verify function signature is built correctly
                    return 'transfer(address,uint256)' === $params['function_selector'];
                })
            )
            ->willReturn([
                'result' => [
                    'result' => true,
                ],
                'transaction' => ['txID' => 'test123'],
            ])
        ;

        $service = new SmartContractService($mockTron);

        $abi = [
            ['name' => 'transfer', 'inputs' => [['type' => 'address'], ['type' => 'uint256']]],
        ];

        $result = $service->triggerSmartContract(
            $abi,
            '41testcontractaddress',
            'transfer',
            ['0x123', 100],
            1000000,
            '41testowneraddress'
        );

        $this->assertIsArray($result);
    }
}
