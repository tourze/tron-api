<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Concerns;

use Tourze\TronAPI\Exception\RuntimeException;
use Tourze\TronAPI\Exception\InvalidArgumentException;
use Tourze\TronAPI\ValueObject\AccountInfo;

/**
 * 账户管理 Trait
 *
 * 提供账户查询、余额查询等功能
 */
trait ManagesAccounts
{
    /**
     * 查询账户信息
     *
     * @return array<string, mixed>
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function getAccount(?string $address = null): array
    {
        $address = $this->getAddressOrDefault($address);

        return $this->manager->request('wallet/getaccount', [
            'address' => $address,
        ]);
    }

    /**
     * 获取地址或使用默认地址
     */
    private function getAddressOrDefault(?string $address): string
    {
        if (!is_null($address)) {
            return $this->toHex($address);
        }

        $hexAddress = $this->address['hex'];
        if (!is_string($hexAddress)) {
            throw new RuntimeException('Default hex address is not properly initialized');
        }

        return $hexAddress;
    }

    /**
     * 查询账户信息（返回 VO 对象）
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function getAccountVO(?string $address = null): AccountInfo
    {
        $data = $this->getAccount($address);

        return AccountInfo::fromArray($data);
    }

    /**
     * @return array<string, mixed>
     */
    public function getWalletAccount(string $address): array
    {
        $address = $this->toHex($address);

        return $this->manager->request('wallet/getaccount', [
            'address' => $address,
        ]);
    }

    /**
     * 获取账户余额
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function getBalance(string $address, bool $fromTron = false): float|int
    {
        $account = $this->getAccount($address);
        $balance = $this->extractBalance($account);

        return $fromTron ? $this->fromTron($balance) : $balance;
    }

    /**
     * 从账户数据中提取余额
     *
     * @param array<string, mixed> $account
     */
    private function extractBalance(array $account): int|float
    {
        if (!array_key_exists('balance', $account)) {
            return 0;
        }

        $balance = $account['balance'];
        if (!is_int($balance) && !is_float($balance) && !is_string($balance)) {
            return 0;
        }

        return is_string($balance) ? (int) $balance : $balance;
    }

    /**
     * 获取 Token 余额
     *
     * @return array<string, mixed>|int
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function getTokenBalance(int $tokenId, string $address, bool $fromTron = false): array|int|float
    {
        return $this->blockchainService->getTokenBalance($tokenId, $address, $fromTron);
    }

    /**
     * 查询带宽信息
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function getBandwidth(?string $address = null)
    {
        $address = $this->getAddressOrDefault($address);

        return $this->manager->request('wallet/getaccountnet', [
            'address' => $address,
        ]);
    }
}
