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
        return new self(
            self::determineSuccess($data),
            self::extractDecodedOutputs($data),
            self::extractConstantResult($data),
            self::extractEnergyUsed($data),
            $data
        );
    }

    /**
     * 提取解码后的输出参数（跳过元数据字段）
     *
     * @param array<string, mixed> $data
     * @return array<int|string, mixed>
     */
    private static function extractDecodedOutputs(array $data): array
    {
        $metadataKeys = ['constant_result', 'Energy_used', 'result', 'transaction'];
        $decodedOutputs = [];

        foreach ($data as $key => $value) {
            if (!in_array($key, $metadataKeys, true)) {
                $decodedOutputs[$key] = $value;
            }
        }

        return $decodedOutputs;
    }

    /**
     * 提取常量调用结果
     *
     * @param array<string, mixed> $data
     */
    private static function extractConstantResult(array $data): ?string
    {
        if (!isset($data['constant_result'])) {
            return null;
        }

        if (!is_array($data['constant_result']) || count($data['constant_result']) === 0) {
            return null;
        }

        /** @var mixed $firstResult */
        $firstResult = $data['constant_result'][0];
        return is_string($firstResult) ? $firstResult : null;
    }

    /**
     * 提取能量消耗值
     *
     * @param array<string, mixed> $data
     */
    private static function extractEnergyUsed(array $data): ?int
    {
        $energyValue = $data['Energy_used'] ?? $data['energy_used'] ?? null;

        if ($energyValue === null) {
            return null;
        }

        return is_numeric($energyValue) ? (int) $energyValue : null;
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
