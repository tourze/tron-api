<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Service;

use Tourze\TronAPI\Exception\InvalidArgumentException;
use Tourze\TronAPI\Exception\RuntimeException;
use Tourze\TronAPI\Tron;

/**
 * 合约管理服务类
 * 负责处理合约的能量限制和设置更新操作
 */
class ContractManagementService
{
    protected Tron $tron;

    public function __construct(Tron $tron)
    {
        $this->tron = $tron;
    }

    /**
     * 更新能量限制
     *
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function updateEnergyLimit(string $contractAddress, int $originEnergyLimit, string $ownerAddress): array
    {
        $contractAddress = $this->tron->address2HexString($contractAddress);
        $ownerAddress = $this->tron->address2HexString($ownerAddress);

        if ($originEnergyLimit < 0 || $originEnergyLimit > 10000000) {
            throw new InvalidArgumentException('Invalid originEnergyLimit provided');
        }

        return $this->tron->getManager()->request('wallet/updateenergylimit', [
            'owner_address' => $this->tron->address2HexString($ownerAddress),
            'contract_address' => $this->tron->address2HexString($contractAddress),
            'origin_energy_limit' => $originEnergyLimit,
        ]);
    }

    /**
     * 更新设置
     *
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function updateSetting(string $contractAddress, int $userFeePercentage, string $ownerAddress): array
    {
        $contractAddress = $this->tron->address2HexString($contractAddress);
        $ownerAddress = $this->tron->address2HexString($ownerAddress);

        if ($userFeePercentage < 0 || $userFeePercentage > 1000) {
            throw new InvalidArgumentException('Invalid userFeePercentage provided');
        }

        return $this->tron->getManager()->request('wallet/updatesetting', [
            'owner_address' => $this->tron->address2HexString($ownerAddress),
            'contract_address' => $this->tron->address2HexString($contractAddress),
            'consume_user_resource_percent' => $userFeePercentage,
        ]);
    }
}
