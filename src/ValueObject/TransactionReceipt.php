<?php

declare(strict_types=1);

namespace Tourze\TronAPI\ValueObject;

use Tourze\TronAPI\Exception\InvalidArgumentException;

/**
 * 交易收据值对象
 * 封装 TRON API getTransactionInfo 响应（交易费用和执行信息）
 */
class TransactionReceipt
{
    /**
     * @param string $id 交易ID
     * @param int $blockNumber 区块号
     * @param int $blockTimestamp 区块时间戳
     * @param string $contractResult 合约执行结果（hex）
     * @param string $contractAddress 合约地址
     * @param string $receipt 收据状态（SUCCESS/FAILED等）
     * @param int $fee 交易费用（sun）
     * @param int $energyFee 能量费用（sun）
     * @param int $energyUsage 能量消耗
     * @param int $netFee 带宽费用（sun）
     * @param int $netUsage 带宽消耗
     * @param array<string, mixed> $rawData 完整原始响应数据
     */
    private function __construct(
        private readonly string $id,
        private readonly int $blockNumber,
        private readonly int $blockTimestamp,
        private readonly string $contractResult,
        private readonly string $contractAddress,
        private readonly string $receipt,
        private readonly int $fee,
        private readonly int $energyFee,
        private readonly int $energyUsage,
        private readonly int $netFee,
        private readonly int $netUsage,
        private readonly array $rawData,
    ) {
    }

    /**
     * 从 API 响应数组创建 TransactionReceipt
     *
     * @param array<string, mixed> $data
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::extractId($data),
            self::extractInt($data, 'blockNumber', 0),
            self::extractInt($data, 'blockTimeStamp', 0),
            self::extractContractResult($data),
            self::extractContractAddress($data),
            self::extractReceipt($data),
            self::extractInt($data, 'fee', 0),
            self::extractInt($data, 'energy_fee', 0),
            self::extractInt($data, 'energy_usage', 0),
            self::extractInt($data, 'net_fee', 0),
            self::extractInt($data, 'net_usage', 0),
            $data
        );
    }

    /**
     * 提取交易ID
     *
     * @param array<string, mixed> $data
     */
    private static function extractId(array $data): string
    {
        if (isset($data['id']) && is_string($data['id'])) {
            return $data['id'];
        }

        return '';
    }

    /**
     * 提取整数字段
     *
     * @param array<string, mixed> $data
     */
    private static function extractInt(array $data, string $key, int $default): int
    {
        if (!isset($data[$key])) {
            return $default;
        }

        if (is_int($data[$key])) {
            return $data[$key];
        }

        if (is_numeric($data[$key])) {
            return (int) $data[$key];
        }

        return $default;
    }

    /**
     * 提取合约执行结果
     *
     * @param array<string, mixed> $data
     */
    private static function extractContractResult(array $data): string
    {
        if (!isset($data['contractResult'])) {
            return '';
        }

        // contractResult 可能是数组或字符串
        if (is_array($data['contractResult']) && count($data['contractResult']) > 0) {
            return is_string($data['contractResult'][0]) ? $data['contractResult'][0] : '';
        }

        if (is_string($data['contractResult'])) {
            return $data['contractResult'];
        }

        return '';
    }

    /**
     * 提取合约地址
     *
     * @param array<string, mixed> $data
     */
    private static function extractContractAddress(array $data): string
    {
        if (isset($data['contract_address']) && is_string($data['contract_address'])) {
            return $data['contract_address'];
        }

        return '';
    }

    /**
     * 提取收据状态
     *
     * @param array<string, mixed> $data
     */
    private static function extractReceipt(array $data): string
    {
        if (!isset($data['receipt'])) {
            return '';
        }

        $receipt = $data['receipt'];
        if (!is_array($receipt)) {
            return '';
        }

        if (isset($receipt['result']) && is_string($receipt['result'])) {
            return $receipt['result'];
        }

        return '';
    }

    /**
     * 获取交易ID
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * 获取区块号
     */
    public function getBlockNumber(): int
    {
        return $this->blockNumber;
    }

    /**
     * 获取区块时间戳
     */
    public function getBlockTimestamp(): int
    {
        return $this->blockTimestamp;
    }

    /**
     * 获取合约执行结果（hex）
     */
    public function getContractResult(): string
    {
        return $this->contractResult;
    }

    /**
     * 获取合约地址
     */
    public function getContractAddress(): string
    {
        return $this->contractAddress;
    }

    /**
     * 获取收据状态
     */
    public function getReceipt(): string
    {
        return $this->receipt;
    }

    /**
     * 判断交易是否成功
     */
    public function isSuccess(): bool
    {
        return 'SUCCESS' === $this->receipt;
    }

    /**
     * 获取交易总费用（sun）
     */
    public function getFee(): int
    {
        return $this->fee;
    }

    /**
     * 获取能量费用（sun）
     */
    public function getEnergyFee(): int
    {
        return $this->energyFee;
    }

    /**
     * 获取能量消耗
     */
    public function getEnergyUsage(): int
    {
        return $this->energyUsage;
    }

    /**
     * 获取带宽费用（sun）
     */
    public function getNetFee(): int
    {
        return $this->netFee;
    }

    /**
     * 获取带宽消耗
     */
    public function getNetUsage(): int
    {
        return $this->netUsage;
    }

    /**
     * 获取日志列表（如果有）
     *
     * @return array<int, array<string, mixed>>
     */
    public function getLogs(): array
    {
        if (!isset($this->rawData['log']) || !is_array($this->rawData['log'])) {
            return [];
        }

        /** @var array<int, array<string, mixed>> $logs */
        $logs = $this->rawData['log'];
        return $logs;
    }

    /**
     * 获取原始响应中的特定字段
     */
    public function getRawField(string $key): mixed
    {
        return $this->rawData[$key] ?? null;
    }

    /**
     * 转换为数组（用于向后兼容）
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->rawData;
    }
}
