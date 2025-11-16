<?php

declare(strict_types=1);

namespace Tourze\TronAPI\ValueObject;

use Tourze\TronAPI\Exception\InvalidArgumentException;

/**
 * Token元数据值对象
 * 聚合Token的基本信息（名称、符号、小数位、总供应量）
 */
class TokenMetadata
{
    public function __construct(
        public readonly string $name,
        public readonly string $symbol,
        public readonly int $decimals,
        public readonly string $totalSupply,
    ) {
        $this->validate();
    }

    /**
     * 从数组创建实例
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: self::ensureString($data['name'] ?? ''),
            symbol: self::ensureString($data['symbol'] ?? ''),
            decimals: self::ensureInt($data['decimals'] ?? 0),
            totalSupply: self::ensureString($data['totalSupply'] ?? '0'),
        );
    }

    /**
     * 转换为数组格式（向后兼容）
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'symbol' => $this->symbol,
            'decimals' => $this->decimals,
            'totalSupply' => $this->totalSupply,
        ];
    }

    /**
     * 获取完整的Token标识（名称 + 符号）
     */
    public function getFullName(): string
    {
        return "{$this->name} ({$this->symbol})";
    }

    /**
     * 获取格式化的总供应量
     */
    public function getFormattedTotalSupply(): string
    {
        $balance = TokenBalance::fromRaw($this->totalSupply, $this->decimals);

        return $balance->format($this->symbol);
    }

    /**
     * 检查是否为标准精度Token（18位小数）
     */
    public function isStandardPrecision(): bool
    {
        return 18 === $this->decimals;
    }

    /**
     * 比较两个Token是否相同（基于符号）
     */
    public function isSameToken(TokenMetadata $other): bool
    {
        return $this->symbol === $other->symbol;
    }

    private function validate(): void
    {
        if ('' === $this->name) {
            throw new InvalidArgumentException('Token name cannot be empty');
        }

        if ('' === $this->symbol) {
            throw new InvalidArgumentException('Token symbol cannot be empty');
        }

        if ($this->decimals < 0 || $this->decimals > 18) {
            throw new InvalidArgumentException('Decimals must be between 0 and 18');
        }

        // 验证总供应量是有效的数字字符串
        if (!preg_match('/^[0-9]+$/', $this->totalSupply)) {
            throw new InvalidArgumentException('Total supply must be a numeric string');
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
