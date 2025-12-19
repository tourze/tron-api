<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\TronAPI\Exception\InvalidArgumentException;
use Tourze\TronAPI\Provider\InMemoryHttpProvider;
use Tourze\TronAPI\Service\ContractManagementService;
use Tourze\TronAPI\Tron;
use Tourze\TronAPI\TronManager;

/**
 * @internal
 */
#[CoversClass(ContractManagementService::class)]
class ContractManagementServiceTest extends TestCase
{
    private const VALID_CONTRACT_ADDRESS = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';
    private const VALID_OWNER_ADDRESS = 'TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY';
    private const HEX_CONTRACT_ADDRESS = '41a614f803b6fd780986a42c78ec9c7f77e6ded13c';
    private const HEX_OWNER_ADDRESS = '41928c9af0651632157ef27a2cf17ca72c575a4d21';

    private InMemoryHttpProvider $fullNodeProvider;

    private Tron $tron;

    private ContractManagementService $service;

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

        $this->service = new ContractManagementService($this->tron);
    }

    public function testCanBeInstantiated(): void
    {
        $tron = new Tron();
        $service = new ContractManagementService($tron);
        $this->assertInstanceOf(ContractManagementService::class, $service);
    }

    public function testUpdateEnergyLimitWithValidParameters(): void
    {
        $expectedResponse = [
            'result' => true,
            'txID' => 'mock_transaction_id',
        ];

        $this->fullNodeProvider->setResponse('wallet/updateenergylimit', $expectedResponse);

        $result = $this->service->updateEnergyLimit(
            self::VALID_CONTRACT_ADDRESS,
            5000000,
            self::VALID_OWNER_ADDRESS
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('result', $result);
        $this->assertTrue($result['result']);
        $this->assertArrayHasKey('txID', $result);

        // 验证请求参数
        $lastRequest = $this->fullNodeProvider->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertStringContainsString('wallet/updateenergylimit', $lastRequest['url']);
        $this->assertSame(self::HEX_OWNER_ADDRESS, $lastRequest['payload']['owner_address']);
        $this->assertSame(self::HEX_CONTRACT_ADDRESS, $lastRequest['payload']['contract_address']);
        $this->assertSame(5000000, $lastRequest['payload']['origin_energy_limit']);
    }

    public function testUpdateEnergyLimitWithMinimumValue(): void
    {
        $this->fullNodeProvider->setResponse('wallet/updateenergylimit', ['result' => true]);

        $result = $this->service->updateEnergyLimit(
            self::VALID_CONTRACT_ADDRESS,
            0,
            self::VALID_OWNER_ADDRESS
        );

        $this->assertIsArray($result);
        $this->assertTrue($result['result']);

        // 验证请求参数
        $lastRequest = $this->fullNodeProvider->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertSame(0, $lastRequest['payload']['origin_energy_limit']);
    }

    public function testUpdateEnergyLimitWithMaximumValue(): void
    {
        $this->fullNodeProvider->setResponse('wallet/updateenergylimit', ['result' => true]);

        $result = $this->service->updateEnergyLimit(
            self::VALID_CONTRACT_ADDRESS,
            10000000,
            self::VALID_OWNER_ADDRESS
        );

        $this->assertIsArray($result);
        $this->assertTrue($result['result']);

        // 验证请求参数
        $lastRequest = $this->fullNodeProvider->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertSame(10000000, $lastRequest['payload']['origin_energy_limit']);
    }

    public function testUpdateEnergyLimitThrowsExceptionForNegativeValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid originEnergyLimit provided');

        $this->service->updateEnergyLimit(
            self::VALID_CONTRACT_ADDRESS,
            -1,
            self::VALID_OWNER_ADDRESS
        );
    }

    public function testUpdateEnergyLimitThrowsExceptionForValueExceedingMaximum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid originEnergyLimit provided');

        $this->service->updateEnergyLimit(
            self::VALID_CONTRACT_ADDRESS,
            10000001,
            self::VALID_OWNER_ADDRESS
        );
    }

    public function testUpdateSettingWithValidParameters(): void
    {
        $expectedResponse = [
            'result' => true,
            'txID' => 'mock_transaction_id',
        ];

        $this->fullNodeProvider->setResponse('wallet/updatesetting', $expectedResponse);

        $result = $this->service->updateSetting(
            self::VALID_CONTRACT_ADDRESS,
            100,
            self::VALID_OWNER_ADDRESS
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('result', $result);
        $this->assertTrue($result['result']);
        $this->assertArrayHasKey('txID', $result);

        // 验证请求参数
        $lastRequest = $this->fullNodeProvider->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertStringContainsString('wallet/updatesetting', $lastRequest['url']);
        $this->assertSame(self::HEX_OWNER_ADDRESS, $lastRequest['payload']['owner_address']);
        $this->assertSame(self::HEX_CONTRACT_ADDRESS, $lastRequest['payload']['contract_address']);
        $this->assertSame(100, $lastRequest['payload']['consume_user_resource_percent']);
    }

    public function testUpdateSettingWithMinimumValue(): void
    {
        $this->fullNodeProvider->setResponse('wallet/updatesetting', ['result' => true]);

        $result = $this->service->updateSetting(
            self::VALID_CONTRACT_ADDRESS,
            0,
            self::VALID_OWNER_ADDRESS
        );

        $this->assertIsArray($result);
        $this->assertTrue($result['result']);

        // 验证请求参数
        $lastRequest = $this->fullNodeProvider->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertSame(0, $lastRequest['payload']['consume_user_resource_percent']);
    }

    public function testUpdateSettingWithMaximumValue(): void
    {
        $this->fullNodeProvider->setResponse('wallet/updatesetting', ['result' => true]);

        $result = $this->service->updateSetting(
            self::VALID_CONTRACT_ADDRESS,
            1000,
            self::VALID_OWNER_ADDRESS
        );

        $this->assertIsArray($result);
        $this->assertTrue($result['result']);

        // 验证请求参数
        $lastRequest = $this->fullNodeProvider->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertSame(1000, $lastRequest['payload']['consume_user_resource_percent']);
    }

    public function testUpdateSettingThrowsExceptionForNegativeValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid userFeePercentage provided');

        $this->service->updateSetting(
            self::VALID_CONTRACT_ADDRESS,
            -1,
            self::VALID_OWNER_ADDRESS
        );
    }

    public function testUpdateSettingThrowsExceptionForValueExceedingMaximum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid userFeePercentage provided');

        $this->service->updateSetting(
            self::VALID_CONTRACT_ADDRESS,
            1001,
            self::VALID_OWNER_ADDRESS
        );
    }

    public function testUpdateEnergyLimitWithDifferentAddresses(): void
    {
        $contractAddress1 = 'TContract1Address1234567890';
        $ownerAddress1 = 'TOwner1Address1234567890';

        $this->fullNodeProvider->setResponse('wallet/updateenergylimit', ['result' => true]);

        $result = $this->service->updateEnergyLimit(
            $contractAddress1,
            1000,
            $ownerAddress1
        );

        $this->assertIsArray($result);
        $this->assertTrue($result['result']);

        // 验证请求参数 - 地址应该被转换为hex格式
        $lastRequest = $this->fullNodeProvider->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertArrayHasKey('owner_address', $lastRequest['payload']);
        $this->assertArrayHasKey('contract_address', $lastRequest['payload']);
        $this->assertSame(1000, $lastRequest['payload']['origin_energy_limit']);
    }

    public function testUpdateSettingWithDifferentAddresses(): void
    {
        $contractAddress2 = 'TContract2Address9876543210';
        $ownerAddress2 = 'TOwner2Address9876543210';

        $this->fullNodeProvider->setResponse('wallet/updatesetting', ['result' => true]);

        $result = $this->service->updateSetting(
            $contractAddress2,
            500,
            $ownerAddress2
        );

        $this->assertIsArray($result);
        $this->assertTrue($result['result']);

        // 验证请求参数 - 地址应该被转换为hex格式
        $lastRequest = $this->fullNodeProvider->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertArrayHasKey('owner_address', $lastRequest['payload']);
        $this->assertArrayHasKey('contract_address', $lastRequest['payload']);
        $this->assertSame(500, $lastRequest['payload']['consume_user_resource_percent']);
    }
}
