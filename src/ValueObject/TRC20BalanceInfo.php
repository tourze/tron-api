<?php

declare(strict_types=1);

namespace Tourze\TronAPI\ValueObject;

use Tourze\TronAPI\Exception\InvalidArgumentException;

/**
 * TRC20 Token 余额信息值对象
 * 封装 TokenQueryService::contractbalance 返回的单个Token余额数据
 */
class TRC20BalanceInfo
{
    /**
     * @param string $name Token名称
     * @param string $symbol Token符号
     * @param float $balance 余额（已除以decimals）
     * @param string $value 原始余额值（最小单位）
     * @param int $decimals 小数位数
     */
    private function __construct(
        private readonly string $name,
        private readonly string $symbol,
        private readonly float $balance,
        private readonly string $value,
        private readonly int $decimals,
    ) {
    }

    /**
     * 从数组创建 TRC20BalanceInfo
     *
     * @param array<string, mixed> $data
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::extractString($data, 'name'),
            self::extractString($data, 'symbol'),
            self::extractFloat($data, 'balance'),
            self::extractString($data, 'value'),
            self::extractInt($data, 'decimals', 0)
        );
    }

    /**
     * 提取字符串字段
     *
     * @param array<string, mixed> $data
     */
    private static function extractString(array $data, string $key): string
    {
        if (!isset($data[$key])) {
            return '';
        }

        $value = $data[$key];
        if (is_string($value)) {
            return $value;
        }

        // Type-safe conversion: only convert numeric or scalar values
        if (is_numeric($value) || is_scalar($value)) {
            return (string) $value;
        }

        return '';
    }

    /**
     * 提取浮点数字段
     *
     * @param array<string, mixed> $data
     */
    private static function extractFloat(array $data, string $key): float
    {
        if (!isset($data[$key])) {
            return 0.0;
        }

        if (is_float($data[$key]) || is_int($data[$key])) {
            return (float) $data[$key];
        }

        if (is_numeric($data[$key])) {
            return (float) $data[$key];
        }

        return 0.0;
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
     * 获取Token名称
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * 获取Token符号
     */
    public function getSymbol(): string
    {
        return $this->symbol;
    }

    /**
     * 获取余额（已除以decimals）
     */
    public function getBalance(): float
    {
        return $this->balance;
    }

    /**
     * 获取原始余额值（最小单位）
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * 获取小数位数
     */
    public function getDecimals(): int
    {
        return $this->decimals;
    }

    /**
     * 判断余额是否为零
     */
    public function isZero(): bool
    {
        return $this->balance <= 0.0;
    }

    /**
     * 转换为数组
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'symbol' => $this->symbol,
            'balance' => $this->balance,
            'value' => $this->value,
            'decimals' => $this->decimals,
        ];
    }
}
