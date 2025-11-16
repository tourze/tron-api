<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Service;

use Tourze\TronAPI\Exception\InvalidArgumentException;
use Tourze\TronAPI\Exception\RuntimeException;
use Tourze\TronAPI\Tron;

/**
 * 资源管理服务类
 * 负责处理带宽和能量的冻结/解冻操作
 */
class ResourceService
{
    protected Tron $tron;

    public function __construct(Tron $tron)
    {
        $this->tron = $tron;
    }

    /**
     * 冻结余额获取带宽或能量
     *
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function freezeBalance(float $amount = 0, int $duration = 3, string $resource = 'BANDWIDTH', ?string $address = null): array
    {
        if (null === $address || '' === $address) {
            throw new InvalidArgumentException('Address not specified');
        }

        if (!in_array($resource, ['BANDWIDTH', 'ENERGY'], true)) {
            throw new InvalidArgumentException('Invalid resource provided: Expected "BANDWIDTH" or "ENERGY"');
        }

        if ($duration < 3) {
            throw new InvalidArgumentException('Invalid duration provided, minimum of 3 days');
        }

        $options = [
            'owner_address' => $this->tron->address2HexString($address),
            'frozen_balance' => $this->tron->toTron($amount),
            'frozen_duration' => $duration,
            'resource' => $resource,
        ];

        return $this->tron->getManager()->request('wallet/freezebalance', $options);
    }

    /**
     * 解冻余额
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    public function unfreezeBalance(string $resource = 'BANDWIDTH', ?string $owner_address = null): array
    {
        if (null === $owner_address || '' === $owner_address) {
            throw new InvalidArgumentException('Owner Address not specified');
        }

        if (!in_array($resource, ['BANDWIDTH', 'ENERGY'], true)) {
            throw new InvalidArgumentException('Invalid resource provided: Expected "BANDWIDTH" or "ENERGY"');
        }

        $options = [
            'owner_address' => $this->tron->address2HexString($owner_address),
            'resource' => $resource,
        ];

        return $this->tron->getManager()->request('wallet/unfreezebalance', $options);
    }
}
