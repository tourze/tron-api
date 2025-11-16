<?php

declare(strict_types=1);

namespace Tourze\TronAPI\ValueObject;

use Tourze\TronAPI\Exception\InvalidArgumentException;

/**
 * 智能合约调用结果值对象
 * 封装 triggerSmartContract/triggerConstantContract 响应
 */
class ContractCallResult
{
    /**
     * @param bool $success 调用是否成功
     * @param array<int|string, mixed> $decodedOutputs 解码后的输出参数
     * @param string|null $constantResult 常量调用的原始结果（hex）
     * @param int|null $energyUsed 消耗的能量
     * @param array<string, mixed> $rawData 完整原始响应数据
     */
    private function __construct(
        private readonly bool $success,
        private readonly array $decodedOutputs,
        private readonly ?string $constantResult,
        private readonly ?int $energyUsed,
        private readonly array $rawData,
    ) {
    }

    /**
     * 从 API 响应数组创建 ContractCallResult
     *
     * @param array<string, mixed> $data
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        // 提取解码后的输出（通常是索引数组）
        $decodedOutputs = [];
        foreach ($data as $key => $value) {
            // 跳过元数据字段
            if (in_array($key, ['constant_result', 'Energy_used', 'result', 'transaction'], true)) {
                continue;
            }
            $decodedOutputs[$key] = $value;
        }

        // 提取常量调用结果
        $constantResult = null;
        if (isset($data['constant_result']) && is_array($data['constant_result']) && count($data['constant_result']) > 0) {
            $constantResult = is_string($data['constant_result'][0]) ? $data['constant_result'][0] : null;
        }

        // 提取能量消耗
        $energyUsed = null;
        if (isset($data['Energy_used'])) {
            $energyUsed = is_numeric($data['Energy_used']) ? (int) $data['Energy_used'] : null;
        } elseif (isset($data['energy_used'])) {
            $energyUsed = is_numeric($data['energy_used']) ? (int) $data['energy_used'] : null;
        }

        // 判断调用是否成功
        $success = self::determineSuccess($data);

        return new self(
            $success,
            $decodedOutputs,
            $constantResult,
            $energyUsed,
            $data
        );
    }

    /**
     * 判断合约调用是否成功
     *
     * @param array<string, mixed> $data
     */
    private static function determineSuccess(array $data): bool
    {
        // 检查 result 字段
        if (isset($data['result'])) {
            if (is_array($data['result']) && isset($data['result']['result'])) {
                return (bool) $data['result']['result'];
            }
            if (is_bool($data['result'])) {
                return $data['result'];
            }
        }

        // 如果有常量结果，通常表示成功
        if (isset($data['constant_result']) && is_array($data['constant_result']) && count($data['constant_result']) > 0) {
            return true;
        }

        // 默认失败
        return false;
    }

    /**
     * 调用是否成功
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * 获取解码后的输出参数
     *
     * @return array<int|string, mixed>
     */
    public function getDecodedOutputs(): array
    {
        return $this->decodedOutputs;
    }

    /**
     * 获取特定索引的输出值
     *
     * @param int|string $index
     * @return mixed
     */
    public function getOutput(int|string $index): mixed
    {
        return $this->decodedOutputs[$index] ?? null;
    }

    /**
     * 获取第一个输出值（最常用场景）
     *
     * @return mixed
     */
    public function getFirstOutput(): mixed
    {
        return $this->decodedOutputs[0] ?? null;
    }

    /**
     * 获取常量调用的原始结果（hex）
     */
    public function getConstantResult(): ?string
    {
        return $this->constantResult;
    }

    /**
     * 获取消耗的能量
     */
    public function getEnergyUsed(): ?int
    {
        return $this->energyUsed;
    }

    /**
     * 获取交易哈希（如果有）
     */
    public function getTransactionHash(): ?string
    {
        if (!isset($this->rawData['transaction'])) {
            return null;
        }

        $transaction = $this->rawData['transaction'];
        if (!is_array($transaction)) {
            return null;
        }

        if (isset($transaction['txID']) && is_string($transaction['txID'])) {
            return $transaction['txID'];
        }

        return null;
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
