<?php

declare(strict_types=1);

namespace Tourze\TronAPI\ValueObject;

use Tourze\TronAPI\Exception\InvalidArgumentException;

/**
 * 合约事件数据值对象
 * 封装区块链事件查询结果
 */
class EventData
{
    /**
     * @param string $eventName 事件名称
     * @param string $contractAddress 合约地址
     * @param int $blockNumber 区块号
     * @param int $timestamp 时间戳
     * @param array<string, mixed> $result 事件结果数据
     * @param string|null $transactionId 交易ID（可选）
     */
    public function __construct(
        public readonly string $eventName,
        public readonly string $contractAddress,
        public readonly int $blockNumber,
        public readonly int $timestamp,
        public readonly array $result,
        public readonly ?string $transactionId = null,
    ) {
        $this->validate();
    }

    /**
     * 从API响应数组创建实例
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        // 支持多种API响应格式
        $eventName = $data['event_name'] ?? $data['eventName'] ?? $data['event'] ?? '';
        $contractAddress = $data['contract_address'] ?? $data['contractAddress'] ?? $data['address'] ?? '';
        $blockNumber = $data['block_number'] ?? $data['blockNumber'] ?? $data['block'] ?? 0;
        $timestamp = $data['timestamp'] ?? $data['block_timestamp'] ?? $data['time'] ?? 0;

        $result = $data['result'] ?? [];
        if (!is_array($result)) {
            $result = [];
        }
        /** @var array<string, mixed> $result */

        return new self(
            eventName: self::ensureString($eventName),
            contractAddress: self::ensureString($contractAddress),
            blockNumber: self::ensureInt($blockNumber),
            timestamp: self::ensureInt($timestamp),
            result: $result,
            transactionId: isset($data['transaction_id']) || isset($data['transactionId'])
                ? self::ensureString($data['transaction_id'] ?? $data['transactionId'])
                : null,
        );
    }

    /**
     * 批量从数组创建实例集合
     *
     * @param array<int, array<string, mixed>> $dataList
     * @return array<int, EventData>
     */
    public static function fromArrayBatch(array $dataList): array
    {
        return array_map(
            fn (array $data) => self::fromArray($data),
            $dataList
        );
    }

    /**
     * 转换为数组格式（向后兼容）
     *
     * 注意：为保持向后兼容，使用简化的键名，并只输出有意义的字段
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [];

        // 使用简化的键名以匹配原始API响应格式
        if ('' !== $this->eventName) {
            $data['event'] = $this->eventName;
        }

        if ('' !== $this->contractAddress) {
            $data['contract_address'] = $this->contractAddress;
        }

        if ($this->blockNumber > 0) {
            $data['block_number'] = $this->blockNumber;
        }

        if ($this->timestamp > 0) {
            $data['timestamp'] = $this->timestamp;
        }

        // result字段始终输出（即使为空数组）
        $data['result'] = $this->result;

        if (null !== $this->transactionId && '' !== $this->transactionId) {
            $data['transaction_id'] = $this->transactionId;
        }

        return $data;
    }

    /**
     * 获取事件结果中的特定字段
     *
     * @param string $key
     * @return mixed
     */
    public function getResultField(string $key): mixed
    {
        return $this->result[$key] ?? null;
    }

    /**
     * 检查事件结果中是否包含特定字段
     */
    public function hasResultField(string $key): bool
    {
        return array_key_exists($key, $this->result);
    }

    /**
     * 获取格式化的时间戳（ISO 8601）
     */
    public function getFormattedTimestamp(): string
    {
        return date('c', (int) ($this->timestamp / 1000));
    }

    /**
     * 比较两个事件是否相同（基于交易ID和事件名称）
     */
    public function isSameEvent(EventData $other): bool
    {
        if (null !== $this->transactionId && null !== $other->transactionId) {
            return $this->transactionId === $other->transactionId
                && $this->eventName === $other->eventName;
        }

        // 如果没有交易ID，基于区块号和时间戳比较
        return $this->blockNumber === $other->blockNumber
            && $this->timestamp === $other->timestamp
            && $this->eventName === $other->eventName
            && $this->contractAddress === $other->contractAddress;
    }

    private function validate(): void
    {
        // 注意：允许eventName和contractAddress为空，以支持不完整的API响应或测试数据
        // 实际使用时应确保这些字段有值

        if ($this->blockNumber < 0) {
            throw new InvalidArgumentException('Block number must be non-negative');
        }

        if ($this->timestamp < 0) {
            throw new InvalidArgumentException('Timestamp must be non-negative');
        }

        // 验证交易ID格式（如果提供且非空）
        if (null !== $this->transactionId && '' !== $this->transactionId && !preg_match('/^[0-9a-fA-F]{64}$/', $this->transactionId)) {
            throw new InvalidArgumentException('Invalid transaction ID format');
        }
    }

    /**
     * 确保值为字符串类型
     */
    private static function ensureString(mixed $value): string
    {
        if (is_string($value)) {
            return trim($value);
        }

        if (is_null($value) || is_scalar($value)) {
            return trim((string) $value);
        }

        throw new InvalidArgumentException('Value must be convertible to string');
    }

    /**
     * 确保值为整数类型
     */
    private static function ensureInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        throw new InvalidArgumentException('Value must be convertible to integer');
    }
}
