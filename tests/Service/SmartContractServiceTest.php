<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\TronAPI\Exception\InvalidArgumentException;
use Tourze\TronAPI\Exception\RuntimeException;
use Tourze\TronAPI\Provider\InMemoryHttpProvider;
use Tourze\TronAPI\Service\SmartContractService;
use Tourze\TronAPI\Tron;
use Tourze\TronAPI\TronManager;
use Tourze\TronAPI\ValueObject\ContractCallResult;

/**
 * @internal
 */
#[CoversClass(SmartContractService::class)]
class SmartContractServiceTest extends TestCase
{
    private InMemoryHttpProvider $fullNodeProvider;

    private Tron $tron;

    private SmartContractService $service;

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

        $this->service = new SmartContractService($this->tron);
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

        $abi = [
            ['name' => 'transfer', 'inputs' => [['type' => 'address'], ['type' => 'uint256']]],
        ];

        $this->service->triggerSmartContract(
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

        $abi = [
            ['name' => 'transfer', 'inputs' => [['type' => 'address'], ['type' => 'uint256']]],
        ];

        $this->service->triggerSmartContract(
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

        $abi = [
            ['name' => 'transfer', 'inputs' => [['type' => 'address'], ['type' => 'uint256']]],
        ];

        $this->service->triggerSmartContract(
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

        $abi = [
            ['name' => 'transfer', 'inputs' => [['type' => 'address'], ['type' => 'uint256']]],
        ];

        $this->service->triggerSmartContract(
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

        $abi = [
            ['name' => 'balanceOf', 'inputs' => [['type' => 'address']]],
        ];

        $this->service->triggerConstantContract(
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

        $abi = [
            ['name' => 'balanceOf', 'inputs' => [['type' => 'address']]],
        ];

        $this->service->triggerConstantContract(
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

        $abi = [
            ['name' => 'balanceOf', 'inputs' => [['type' => 'address']]],
        ];

        $this->service->triggerConstantContract(
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

        $this->fullNodeProvider->setResponse('wallet/triggersmartcontract', []); // No result field

        $abi = [
            ['name' => 'transfer', 'inputs' => [['type' => 'address'], ['type' => 'uint256']]],
        ];

        $this->service->triggerSmartContract(
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

        $this->fullNodeProvider->setResponse('wallet/triggersmartcontract', [
            'result' => [
                'message' => '48656c6c6f', // "Hello" in hex
            ],
        ]);

        $abi = [
            ['name' => 'transfer', 'inputs' => [['type' => 'address'], ['type' => 'uint256']]],
        ];

        $this->service->triggerSmartContract(
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
        $this->fullNodeProvider->setResponse('wallet/triggerconstantcontract', [
            'result' => [
                'result' => true,
            ],
            'transaction' => ['txID' => 'test123'],
        ]);

        $abi = [
            ['name' => 'totalSupply', 'inputs' => []],
        ];

        $result = $this->service->triggerConstantContract(
            $abi,
            '41testcontractaddress',
            'totalSupply',
            [] // No params needed
        );

        $this->assertIsArray($result);
    }

    public function testTriggerConstantContractWithDefaultAddress(): void
    {
        $this->fullNodeProvider->setResponse('wallet/triggerconstantcontract', [
            'result' => [
                'result' => true,
            ],
            'transaction' => ['txID' => 'test123'],
        ]);

        $abi = [
            ['name' => 'totalSupply', 'inputs' => []],
        ];

        // Use default address
        $result = $this->service->triggerConstantContract(
            $abi,
            '41testcontractaddress',
            'totalSupply'
        );

        $this->assertIsArray($result);

        // 验证请求使用了默认地址
        $lastRequest = $this->fullNodeProvider->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertSame('410000000000000000000000000000000000000000', $lastRequest['payload']['owner_address']);
    }

    public function testTriggerSmartContractBuildsCorrectSignature(): void
    {
        $this->fullNodeProvider->setResponse('wallet/triggersmartcontract', [
            'result' => [
                'result' => true,
            ],
            'transaction' => ['txID' => 'test123'],
        ]);

        $abi = [
            ['name' => 'transfer', 'inputs' => [['type' => 'address'], ['type' => 'uint256']]],
        ];

        $result = $this->service->triggerSmartContract(
            $abi,
            '41testcontractaddress',
            'transfer',
            ['0x123', 100],
            1000000,
            '41testowneraddress'
        );

        $this->assertIsArray($result);

        // 验证请求包含正确的函数签名
        $lastRequest = $this->fullNodeProvider->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertSame('transfer(address,uint256)', $lastRequest['payload']['function_selector']);
    }

    public function testTriggerConstantContractVO(): void
    {
        $this->fullNodeProvider->setResponse('wallet/triggerconstantcontract', [
            'result' => [
                'result' => true,
            ],
            'constant_result' => ['0000000000000000000000000000000000000000000000000000000000000064'],
            'Energy_used' => 1000,
        ]);

        $abi = [
            ['name' => 'balanceOf', 'inputs' => [['type' => 'address']], 'outputs' => [['type' => 'uint256']]],
        ];

        $result = $this->service->triggerConstantContractVO(
            $abi,
            '41testcontractaddress',
            'balanceOf',
            ['0x123']
        );

        $this->assertInstanceOf(ContractCallResult::class, $result);
        $this->assertTrue($result->isSuccess());
        $this->assertIsArray($result->toArray());
    }

    public function testTriggerSmartContractVO(): void
    {
        $this->fullNodeProvider->setResponse('wallet/triggersmartcontract', [
            'result' => [
                'result' => true,
            ],
            'transaction' => ['txID' => 'test456'],
        ]);

        $abi = [
            ['name' => 'transfer', 'inputs' => [['type' => 'address'], ['type' => 'uint256']]],
        ];

        $result = $this->service->triggerSmartContractVO(
            $abi,
            '41testcontractaddress',
            'transfer',
            ['0x123', 100],
            1000000,
            '41testowneraddress'
        );

        $this->assertInstanceOf(ContractCallResult::class, $result);
        $this->assertTrue($result->isSuccess());
        $this->assertIsArray($result->toArray());
    }
}
