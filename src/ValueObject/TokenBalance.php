<?php

declare(strict_types=1);

namespace Tourze\TronAPI\ValueObject;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Tourze\TronAPI\Exception\InvalidArgumentException;

/**
 * Token余额值对象
 * 提供类型安全的余额表示和转换
 */
class TokenBalance
{
    /**
     * @param string $rawBalance 原始余额（最小单位，未缩放）
     * @param int $decimals Token的小数位数
     * @param string|null $scaledBalance 已缩放的余额（可选，用于缓存）
     */
    public function __construct(
        public readonly string $rawBalance,
        public readonly int $decimals,
        private ?string $scaledBalance = null,
    ) {
        $this->validate();
    }

    /**
     * 从原始余额创建实例
     */
    public static function fromRaw(string $rawBalance, int $decimals): self
    {
        return new self($rawBalance, $decimals);
    }

    /**
     * 从已缩放的余额创建实例
     */
    public static function fromScaled(string $scaledBalance, int $decimals): self
    {
        // 将缩放后的值转换回原始值
        $rawBalance = BigDecimal::of($scaledBalance)
            ->multipliedBy(BigDecimal::of(10)->power($decimals))
            ->toScale(0, RoundingMode::DOWN)
            ->__toString()
        ;

        return new self($rawBalance, $decimals, $scaledBalance);
    }

    /**
     * 获取原始余额（最小单位）
     */
    public function getRaw(): string
    {
        return $this->rawBalance;
    }

    /**
     * 获取已缩放的余额（考虑小数位）
     */
    public function getScaled(): string
    {
        if (null !== $this->scaledBalance) {
            return $this->scaledBalance;
        }

        $this->scaledBalance = $this->calculateScaledBalance();

        return $this->scaledBalance;
    }

    /**
     * 转换为浮点数（注意：大额可能损失精度）
     */
    public function toFloat(): float
    {
        return (float) $this->getScaled();
    }

    /**
     * 检查余额是否为零
     */
    public function isZero(): bool
    {
        return '0' === $this->rawBalance;
    }

    /**
     * 检查余额是否为正
     */
    public function isPositive(): bool
    {
        return BigDecimal::of($this->rawBalance)->isPositive();
    }

    /**
     * 比较两个余额
     *
     * @return int -1 if less than, 0 if equal, 1 if greater than
     */
    public function compareTo(TokenBalance $other): int
    {
        if ($this->decimals !== $other->decimals) {
            throw new InvalidArgumentException('Cannot compare balances with different decimals');
        }

        return BigDecimal::of($this->rawBalance)->compareTo(BigDecimal::of($other->rawBalance));
    }

    /**
     * 格式化为字符串（带符号）
     */
    public function format(string $symbol = ''): string
    {
        $scaled = $this->getScaled();

        return '' !== $symbol ? "{$scaled} {$symbol}" : $scaled;
    }

    /**
     * 转换为数组格式（向后兼容）
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'raw' => $this->rawBalance,
            'scaled' => $this->getScaled(),
            'decimals' => $this->decimals,
        ];
    }

    /**
     * 返回字符串表示（已缩放的余额）
     */
    public function __toString(): string
    {
        return $this->getScaled();
    }

    private function validate(): void
    {
        // 验证原始余额是有效的数字字符串
        if (!preg_match('/^[0-9]+$/', $this->rawBalance)) {
            throw new InvalidArgumentException('Raw balance must be a numeric string');
        }

        // 验证小数位数
        if ($this->decimals < 0 || $this->decimals > 18) {
            throw new InvalidArgumentException('Decimals must be between 0 and 18');
        }
    }

    private function calculateScaledBalance(): string
    {
        return BigDecimal::of($this->rawBalance)
            ->dividedBy(BigDecimal::of(10)->power($this->decimals), $this->decimals, RoundingMode::DOWN)
            ->toScale($this->decimals, RoundingMode::DOWN)
            ->__toString()
        ;
    }
}
