<?php

declare(strict_types=1);

namespace Tourze\TronAPI\ValueObject;

use Tourze\TronAPI\Exception\InvalidArgumentException;

/**
 * Transaction 信息值对象
 * 封装 TRON API getTransaction/sendTransaction 响应
 */
class TransactionInfo
{
    /**
     * @param string $txID 交易ID
     * @param array<string, mixed> $rawData 交易原始数据
     * @param array<int, string> $signature 签名列表
     * @param bool $visible 是否可见
     * @param array<string, mixed> $ret 交易结果
     * @param array<string, mixed> $fullData 完整原始响应数据
     */
    private function __construct(
        private readonly string $txID,
        private readonly array $rawData,
        private readonly array $signature,
        private readonly bool $visible,
        private readonly array $ret,
        private readonly array $fullData,
    ) {
    }

    /**
     * 从 API 响应数组创建 TransactionInfo
     *
     * @param array<string, mixed> $data
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        // txID 字段可选（某些场景下不返回）
        $txID = '';
        if (isset($data['txID'])) {
            if (!is_string($data['txID'])) {
                throw new InvalidArgumentException('Transaction ID must be a string');
            }
            $txID = $data['txID'];
        }

        // raw_data 字段可选，默认为空数组
        $rawData = [];
        if (isset($data['raw_data']) && is_array($data['raw_data'])) {
            $rawData = $data['raw_data'];
        }
        /** @var array<string, mixed> $rawData */

        // signature 字段可选，默认为空数组
        $signature = [];
        if (isset($data['signature']) && is_array($data['signature'])) {
            // 确保签名都是字符串
            $signature = array_values(array_filter($data['signature'], 'is_string'));
        }

        // visible 字段可选，默认为 false
        $visible = false;
        if (isset($data['visible'])) {
            $visible = (bool) $data['visible'];
        }

        // ret 字段可选，默认为空数组
        $ret = [];
        if (isset($data['ret']) && is_array($data['ret'])) {
            $ret = $data['ret'];
        }
        /** @var array<string, mixed> $ret */

        return new self(
            $txID,
            $rawData,
            $signature,
            $visible,
            $ret,
            $data
        );
    }

    /**
     * 获取交易ID
     */
    public function getTxID(): string
    {
        return $this->txID;
    }

    /**
     * 获取交易原始数据
     *
     * @return array<string, mixed>
     */
    public function getRawData(): array
    {
        return $this->rawData;
    }

    /**
     * 获取签名列表
     *
     * @return array<int, string>
     */
    public function getSignature(): array
    {
        return $this->signature;
    }

    /**
     * 判断交易是否可见
     */
    public function isVisible(): bool
    {
        return $this->visible;
    }

    /**
     * 获取交易结果
     *
     * @return array<string, mixed>
     */
    public function getRet(): array
    {
        return $this->ret;
    }

    /**
     * 判断交易是否成功
     */
    public function isSuccess(): bool
    {
        if ([] === $this->ret) {
            return false;
        }

        foreach ($this->ret as $result) {
            if (!is_array($result)) {
                continue;
            }
            if (isset($result['contractRet']) && 'SUCCESS' === $result['contractRet']) {
                return true;
            }
        }

        return false;
    }

    /**
     * 获取合约地址（如果存在）
     */
    public function getContractAddress(): ?string
    {
        if (!isset($this->rawData['contract']) || !is_array($this->rawData['contract'])) {
            return null;
        }

        foreach ($this->rawData['contract'] as $contract) {
            $address = $this->extractContractAddressFromContract($contract);
            if (null !== $address) {
                return $address;
            }
        }

        return null;
    }

    /**
     * 从单个合约数据中提取合约地址
     *
     * @param mixed $contract
     */
    private function extractContractAddressFromContract(mixed $contract): ?string
    {
        if (!is_array($contract)) {
            return null;
        }

        if (!isset($contract['parameter']) || !is_array($contract['parameter'])) {
            return null;
        }

        if (!isset($contract['parameter']['value']) || !is_array($contract['parameter']['value'])) {
            return null;
        }

        if (!isset($contract['parameter']['value']['contract_address'])) {
            return null;
        }

        $addr = $contract['parameter']['value']['contract_address'];

        return is_string($addr) ? $addr : null;
    }

    /**
     * 获取交易类型（如果存在）
     */
    public function getType(): ?string
    {
        if (isset($this->rawData['contract']) && is_array($this->rawData['contract'])) {
            foreach ($this->rawData['contract'] as $contract) {
                if (!is_array($contract)) {
                    continue;
                }
                if (isset($contract['type']) && is_string($contract['type'])) {
                    return $contract['type'];
                }
            }
        }

        return null;
    }

    /**
     * 获取原始响应中的特定字段
     */
    public function getRawField(string $key): mixed
    {
        return $this->fullData[$key] ?? null;
    }

    /**
     * 转换为数组（用于向后兼容）
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->fullData;
    }
}
