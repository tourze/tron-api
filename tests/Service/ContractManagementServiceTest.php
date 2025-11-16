<?php

namespace Tourze\TronAPI\Tests\Service;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\TronAPI\Exception\InvalidArgumentException;
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
    private const HEX_OWNER_ADDRESS = '41e472f387585c2b58bc2c9bb4492bc1f17342cd01';

    public function testCanBeInstantiated(): void
    {
        $tron = new Tron();
        $service = new ContractManagementService($tron);
        $this->assertInstanceOf(ContractManagementService::class, $service);
    }

    public function testUpdateEnergyLimitWithValidParameters(): void
    {
        // Mock Tron instance
        $tron = $this->createMock(Tron::class);

        // Mock address2HexString calls - will be called 4 times:
        // 1. Line 34: contractAddress (base58 -> hex)
        // 2. Line 35: ownerAddress (base58 -> hex)
        // 3. Line 42: ownerAddress (hex -> hex, idempotent)
        // 4. Line 43: contractAddress (hex -> hex, idempotent)
        $tron->expects($this->exactly(4))
            ->method('address2HexString')
            ->willReturnCallback(function ($address) {
                if (self::VALID_CONTRACT_ADDRESS === $address || self::HEX_CONTRACT_ADDRESS === $address) {
                    return self::HEX_CONTRACT_ADDRESS;
                }
                if (self::VALID_OWNER_ADDRESS === $address || self::HEX_OWNER_ADDRESS === $address) {
                    return self::HEX_OWNER_ADDRESS;
                }

                return '41unknown';
            })
        ;

        // Mock TronManager
        $mockManager = $this->createMock(TronManager::class);
        $expectedResponse = [
            'result' => true,
            'txID' => 'mock_transaction_id',
        ];

        $mockManager->expects($this->once())
            ->method('request')
            ->with(
                'wallet/updateenergylimit',
                Assert::callback(function ($params) {
                    return self::HEX_OWNER_ADDRESS === $params['owner_address']
                        && self::HEX_CONTRACT_ADDRESS === $params['contract_address']
                        && 5000000 === $params['origin_energy_limit'];
                })
            )
            ->willReturn($expectedResponse)
        ;

        $tron->expects($this->once())
            ->method('getManager')
            ->willReturn($mockManager)
        ;

        // Create service and call updateEnergyLimit
        $service = new ContractManagementService($tron);
        $result = $service->updateEnergyLimit(
            self::VALID_CONTRACT_ADDRESS,
            5000000,
            self::VALID_OWNER_ADDRESS
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('result', $result);
        $this->assertTrue($result['result']);
        $this->assertArrayHasKey('txID', $result);
    }

    public function testUpdateEnergyLimitWithMinimumValue(): void
    {
        $tron = $this->createMock(Tron::class);

        $tron->expects($this->exactly(4))
            ->method('address2HexString')
            ->willReturnCallback(function ($address) {
                if (self::VALID_CONTRACT_ADDRESS === $address || self::HEX_CONTRACT_ADDRESS === $address) {
                    return self::HEX_CONTRACT_ADDRESS;
                }
                if (self::VALID_OWNER_ADDRESS === $address || self::HEX_OWNER_ADDRESS === $address) {
                    return self::HEX_OWNER_ADDRESS;
                }

                return '41unknown';
            })
        ;

        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->with(
                'wallet/updateenergylimit',
                Assert::callback(function ($params) {
                    return 0 === $params['origin_energy_limit'];
                })
            )
            ->willReturn(['result' => true])
        ;

        $tron->expects($this->once())
            ->method('getManager')
            ->willReturn($mockManager)
        ;

        $service = new ContractManagementService($tron);
        $result = $service->updateEnergyLimit(
            self::VALID_CONTRACT_ADDRESS,
            0,
            self::VALID_OWNER_ADDRESS
        );

        $this->assertIsArray($result);
        $this->assertTrue($result['result']);
    }

    public function testUpdateEnergyLimitWithMaximumValue(): void
    {
        $tron = $this->createMock(Tron::class);

        $tron->expects($this->exactly(4))
            ->method('address2HexString')
            ->willReturnCallback(function ($address) {
                if (self::VALID_CONTRACT_ADDRESS === $address || self::HEX_CONTRACT_ADDRESS === $address) {
                    return self::HEX_CONTRACT_ADDRESS;
                }
                if (self::VALID_OWNER_ADDRESS === $address || self::HEX_OWNER_ADDRESS === $address) {
                    return self::HEX_OWNER_ADDRESS;
                }

                return '41unknown';
            })
        ;

        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->with(
                'wallet/updateenergylimit',
                Assert::callback(function ($params) {
                    return 10000000 === $params['origin_energy_limit'];
                })
            )
            ->willReturn(['result' => true])
        ;

        $tron->expects($this->once())
            ->method('getManager')
            ->willReturn($mockManager)
        ;

        $service = new ContractManagementService($tron);
        $result = $service->updateEnergyLimit(
            self::VALID_CONTRACT_ADDRESS,
            10000000,
            self::VALID_OWNER_ADDRESS
        );

        $this->assertIsArray($result);
        $this->assertTrue($result['result']);
    }

    public function testUpdateEnergyLimitThrowsExceptionForNegativeValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid originEnergyLimit provided');

        $tron = $this->createMock(Tron::class);

        $tron->expects($this->exactly(2))
            ->method('address2HexString')
            ->willReturnCallback(function ($address) {
                if (self::VALID_CONTRACT_ADDRESS === $address || self::HEX_CONTRACT_ADDRESS === $address) {
                    return self::HEX_CONTRACT_ADDRESS;
                }
                if (self::VALID_OWNER_ADDRESS === $address || self::HEX_OWNER_ADDRESS === $address) {
                    return self::HEX_OWNER_ADDRESS;
                }

                return '41unknown';
            })
        ;

        $service = new ContractManagementService($tron);
        $service->updateEnergyLimit(
            self::VALID_CONTRACT_ADDRESS,
            -1,
            self::VALID_OWNER_ADDRESS
        );
    }

    public function testUpdateEnergyLimitThrowsExceptionForValueExceedingMaximum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid originEnergyLimit provided');

        $tron = $this->createMock(Tron::class);

        $tron->expects($this->exactly(2))
            ->method('address2HexString')
            ->willReturnCallback(function ($address) {
                if (self::VALID_CONTRACT_ADDRESS === $address || self::HEX_CONTRACT_ADDRESS === $address) {
                    return self::HEX_CONTRACT_ADDRESS;
                }
                if (self::VALID_OWNER_ADDRESS === $address || self::HEX_OWNER_ADDRESS === $address) {
                    return self::HEX_OWNER_ADDRESS;
                }

                return '41unknown';
            })
        ;

        $service = new ContractManagementService($tron);
        $service->updateEnergyLimit(
            self::VALID_CONTRACT_ADDRESS,
            10000001,
            self::VALID_OWNER_ADDRESS
        );
    }

    public function testUpdateSettingWithValidParameters(): void
    {
        // Mock Tron instance
        $tron = $this->createMock(Tron::class);

        // Mock address2HexString calls - will be called 4 times (contract and owner, twice each)
        $tron->expects($this->exactly(4))
            ->method('address2HexString')
            ->willReturnCallback(function ($address) {
                if (self::VALID_CONTRACT_ADDRESS === $address || self::HEX_CONTRACT_ADDRESS === $address) {
                    return self::HEX_CONTRACT_ADDRESS;
                }
                if (self::VALID_OWNER_ADDRESS === $address || self::HEX_OWNER_ADDRESS === $address) {
                    return self::HEX_OWNER_ADDRESS;
                }

                return '41unknown';
            })
        ;

        // Mock TronManager
        $mockManager = $this->createMock(TronManager::class);
        $expectedResponse = [
            'result' => true,
            'txID' => 'mock_transaction_id',
        ];

        $mockManager->expects($this->once())
            ->method('request')
            ->with(
                'wallet/updatesetting',
                Assert::callback(function ($params) {
                    return self::HEX_OWNER_ADDRESS === $params['owner_address']
                        && self::HEX_CONTRACT_ADDRESS === $params['contract_address']
                        && 100 === $params['consume_user_resource_percent'];
                })
            )
            ->willReturn($expectedResponse)
        ;

        $tron->expects($this->once())
            ->method('getManager')
            ->willReturn($mockManager)
        ;

        // Create service and call updateSetting
        $service = new ContractManagementService($tron);
        $result = $service->updateSetting(
            self::VALID_CONTRACT_ADDRESS,
            100,
            self::VALID_OWNER_ADDRESS
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('result', $result);
        $this->assertTrue($result['result']);
        $this->assertArrayHasKey('txID', $result);
    }

    public function testUpdateSettingWithMinimumValue(): void
    {
        $tron = $this->createMock(Tron::class);

        $tron->expects($this->exactly(4))
            ->method('address2HexString')
            ->willReturnCallback(function ($address) {
                if (self::VALID_CONTRACT_ADDRESS === $address || self::HEX_CONTRACT_ADDRESS === $address) {
                    return self::HEX_CONTRACT_ADDRESS;
                }
                if (self::VALID_OWNER_ADDRESS === $address || self::HEX_OWNER_ADDRESS === $address) {
                    return self::HEX_OWNER_ADDRESS;
                }

                return '41unknown';
            })
        ;

        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->with(
                'wallet/updatesetting',
                Assert::callback(function ($params) {
                    return 0 === $params['consume_user_resource_percent'];
                })
            )
            ->willReturn(['result' => true])
        ;

        $tron->expects($this->once())
            ->method('getManager')
            ->willReturn($mockManager)
        ;

        $service = new ContractManagementService($tron);
        $result = $service->updateSetting(
            self::VALID_CONTRACT_ADDRESS,
            0,
            self::VALID_OWNER_ADDRESS
        );

        $this->assertIsArray($result);
        $this->assertTrue($result['result']);
    }

    public function testUpdateSettingWithMaximumValue(): void
    {
        $tron = $this->createMock(Tron::class);

        $tron->expects($this->exactly(4))
            ->method('address2HexString')
            ->willReturnCallback(function ($address) {
                if (self::VALID_CONTRACT_ADDRESS === $address || self::HEX_CONTRACT_ADDRESS === $address) {
                    return self::HEX_CONTRACT_ADDRESS;
                }
                if (self::VALID_OWNER_ADDRESS === $address || self::HEX_OWNER_ADDRESS === $address) {
                    return self::HEX_OWNER_ADDRESS;
                }

                return '41unknown';
            })
        ;

        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->with(
                'wallet/updatesetting',
                Assert::callback(function ($params) {
                    return 1000 === $params['consume_user_resource_percent'];
                })
            )
            ->willReturn(['result' => true])
        ;

        $tron->expects($this->once())
            ->method('getManager')
            ->willReturn($mockManager)
        ;

        $service = new ContractManagementService($tron);
        $result = $service->updateSetting(
            self::VALID_CONTRACT_ADDRESS,
            1000,
            self::VALID_OWNER_ADDRESS
        );

        $this->assertIsArray($result);
        $this->assertTrue($result['result']);
    }

    public function testUpdateSettingThrowsExceptionForNegativeValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid userFeePercentage provided');

        $tron = $this->createMock(Tron::class);

        $tron->expects($this->exactly(2))
            ->method('address2HexString')
            ->willReturnCallback(function ($address) {
                if (self::VALID_CONTRACT_ADDRESS === $address || self::HEX_CONTRACT_ADDRESS === $address) {
                    return self::HEX_CONTRACT_ADDRESS;
                }
                if (self::VALID_OWNER_ADDRESS === $address || self::HEX_OWNER_ADDRESS === $address) {
                    return self::HEX_OWNER_ADDRESS;
                }

                return '41unknown';
            })
        ;

        $service = new ContractManagementService($tron);
        $service->updateSetting(
            self::VALID_CONTRACT_ADDRESS,
            -1,
            self::VALID_OWNER_ADDRESS
        );
    }

    public function testUpdateSettingThrowsExceptionForValueExceedingMaximum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid userFeePercentage provided');

        $tron = $this->createMock(Tron::class);

        $tron->expects($this->exactly(2))
            ->method('address2HexString')
            ->willReturnCallback(function ($address) {
                if (self::VALID_CONTRACT_ADDRESS === $address || self::HEX_CONTRACT_ADDRESS === $address) {
                    return self::HEX_CONTRACT_ADDRESS;
                }
                if (self::VALID_OWNER_ADDRESS === $address || self::HEX_OWNER_ADDRESS === $address) {
                    return self::HEX_OWNER_ADDRESS;
                }

                return '41unknown';
            })
        ;

        $service = new ContractManagementService($tron);
        $service->updateSetting(
            self::VALID_CONTRACT_ADDRESS,
            1001,
            self::VALID_OWNER_ADDRESS
        );
    }

    public function testUpdateEnergyLimitWithDifferentAddresses(): void
    {
        $tron = $this->createMock(Tron::class);

        $contractAddress1 = 'TContract1Address1234567890';
        $ownerAddress1 = 'TOwner1Address1234567890';
        $hexContract1 = '41contract1hex';
        $hexOwner1 = '41owner1hex';

        $tron->expects($this->exactly(4))
            ->method('address2HexString')
            ->willReturnCallback(function ($address) use ($contractAddress1, $ownerAddress1, $hexContract1, $hexOwner1) {
                if ($address === $contractAddress1 || $address === $hexContract1) {
                    return $hexContract1;
                }
                if ($address === $ownerAddress1 || $address === $hexOwner1) {
                    return $hexOwner1;
                }

                return '41unknown';
            })
        ;

        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->with(
                'wallet/updateenergylimit',
                Assert::callback(function ($params) use ($hexContract1, $hexOwner1) {
                    return $params['owner_address'] === $hexOwner1
                        && $params['contract_address'] === $hexContract1;
                })
            )
            ->willReturn(['result' => true])
        ;

        $tron->expects($this->once())
            ->method('getManager')
            ->willReturn($mockManager)
        ;

        $service = new ContractManagementService($tron);
        $result = $service->updateEnergyLimit(
            $contractAddress1,
            1000,
            $ownerAddress1
        );

        $this->assertIsArray($result);
        $this->assertTrue($result['result']);
    }

    public function testUpdateSettingWithDifferentAddresses(): void
    {
        $tron = $this->createMock(Tron::class);

        $contractAddress2 = 'TContract2Address9876543210';
        $ownerAddress2 = 'TOwner2Address9876543210';
        $hexContract2 = '41contract2hex';
        $hexOwner2 = '41owner2hex';

        $tron->expects($this->exactly(4))
            ->method('address2HexString')
            ->willReturnCallback(function ($address) use ($contractAddress2, $ownerAddress2, $hexContract2, $hexOwner2) {
                if ($address === $contractAddress2 || $address === $hexContract2) {
                    return $hexContract2;
                }
                if ($address === $ownerAddress2 || $address === $hexOwner2) {
                    return $hexOwner2;
                }

                return '41unknown';
            })
        ;

        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->with(
                'wallet/updatesetting',
                Assert::callback(function ($params) use ($hexContract2, $hexOwner2) {
                    return $params['owner_address'] === $hexOwner2
                        && $params['contract_address'] === $hexContract2;
                })
            )
            ->willReturn(['result' => true])
        ;

        $tron->expects($this->once())
            ->method('getManager')
            ->willReturn($mockManager)
        ;

        $service = new ContractManagementService($tron);
        $result = $service->updateSetting(
            $contractAddress2,
            500,
            $ownerAddress2
        );

        $this->assertIsArray($result);
        $this->assertTrue($result['result']);
    }
}
