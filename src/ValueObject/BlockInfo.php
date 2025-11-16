<?php

declare(strict_types=1);

namespace Tourze\TronAPI\ValueObject;

use Tourze\TronAPI\Exception\InvalidArgumentException;

/**
 * Block 信息值对象
 * 封装 TRON API getBlock/getBlockByHash/getBlockByNumber 响应
 */
class BlockInfo
{
    /**
     * @param string $blockID 区块ID（hash）
     * @param int $blockNumber 区块高度
     * @param int $timestamp 区块时间戳（毫秒）
     * @param array<int, array<string, mixed>> $transactions 交易列表
     * @param array<string, mixed> $blockHeader 区块头信息
     * @param array<string, mixed> $rawData 原始响应数据（包含所有其他字段）
     */
    private function __construct(
        private readonly string $blockID,
        private readonly int $blockNumber,
        private readonly int $timestamp,
        private readonly array $transactions,
        private readonly array $blockHeader,
        private readonly array $rawData,
    ) {
    }

    /**
     * 从 API 响应数组创建 BlockInfo
     *
     * @param array<string, mixed> $data
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        $blockHeader = self::extractBlockHeader($data);

        return new self(
            self::extractBlockID($data),
            self::extractBlockNumber($blockHeader),
            self::extractTimestamp($blockHeader),
            self::parseTransactions($data),
            $blockHeader,
            $data
        );
    }

    /**
     * 提取区块ID
     *
     * @param array<string, mixed> $data
     * @throws InvalidArgumentException
     */
    private static function extractBlockID(array $data): string
    {
        if (!isset($data['blockID'])) {
            throw new InvalidArgumentException('Block ID is required');
        }

        if (!is_string($data['blockID'])) {
            throw new InvalidArgumentException('Block ID must be a string');
        }

        return $data['blockID'];
    }

    /**
     * 提取区块头信息
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function extractBlockHeader(array $data): array
    {
        if (isset($data['block_header']) && is_array($data['block_header'])) {
            /** @var array<string, mixed> $blockHeader */
            $blockHeader = $data['block_header'];
            return $blockHeader;
        }

        return [];
    }

    /**
     * 从区块头中提取区块高度
     *
     * @param array<string, mixed> $blockHeader
     */
    private static function extractBlockNumber(array $blockHeader): int
    {
        if (!isset($blockHeader['raw_data']) || !is_array($blockHeader['raw_data'])) {
            return 0;
        }

        $rawData = $blockHeader['raw_data'];

        if (isset($rawData['number']) && is_numeric($rawData['number'])) {
            return (int) $rawData['number'];
        }

        return 0;
    }

    /**
     * 从区块头中提取时间戳
     *
     * @param array<string, mixed> $blockHeader
     */
    private static function extractTimestamp(array $blockHeader): int
    {
        if (!isset($blockHeader['raw_data']) || !is_array($blockHeader['raw_data'])) {
            return 0;
        }

        $rawData = $blockHeader['raw_data'];

        if (isset($rawData['timestamp']) && is_numeric($rawData['timestamp'])) {
            return (int) $rawData['timestamp'];
        }

        return 0;
    }

    /**
     * 解析交易列表
     *
     * @param array<string, mixed> $data
     * @return array<int, array<string, mixed>>
     */
    private static function parseTransactions(array $data): array
    {
        if (!isset($data['transactions']) || !is_array($data['transactions'])) {
            return [];
        }

        $filtered = array_filter($data['transactions'], 'is_array');
        /** @var array<int, array<string, mixed>> $transactions */
        $transactions = array_values($filtered);
        return $transactions;
    }

    /**
     * 获取区块ID
     */
    public function getBlockID(): string
    {
        return $this->blockID;
    }

    /**
     * 获取区块高度
     */
    public function getBlockNumber(): int
    {
        return $this->blockNumber;
    }

    /**
     * 获取区块时间戳（毫秒）
     */
    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    /**
     * 获取交易列表
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTransactions(): array
    {
        return $this->transactions;
    }

    /**
     * 获取交易数量
     */
    public function getTransactionCount(): int
    {
        return count($this->transactions);
    }

    /**
     * 判断区块是否包含交易
     */
    public function hasTransactions(): bool
    {
        return count($this->transactions) > 0;
    }

    /**
     * 获取区块头信息
     *
     * @return array<string, mixed>
     */
    public function getBlockHeader(): array
    {
        return $this->blockHeader;
    }

    /**
     * 获取指定索引的交易
     *
     * @param int $index
     * @return array<string, mixed>|null
     */
    public function getTransaction(int $index): ?array
    {
        if ($index < 0 || $index >= count($this->transactions)) {
            return null;
        }

        return $this->transactions[$index];
    }

    /**
     * 获取原始响应中的特定字段
     *
     * @param string $key
     * @return mixed
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
