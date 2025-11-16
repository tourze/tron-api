<?php

declare(strict_types=1);

namespace Tourze\TronAPI\ValueObject;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Tourze\TronAPI\Exception\InvalidArgumentException;

/**
 * TRX金额值对象
 * 提供类型安全的TRX金额表示和转换
 * TRX固定使用6位小数（1 TRX = 1,000,000 SUN）
 */
class TronAmount
{
    /**
     * TRX的固定小数位数
     */
    private const DECIMALS = 6;

    /**
     * 1 TRX = 1,000,000 SUN
     */
    private const SUN_PER_TRX = '1000000';

    /**
     * @param string $sun 以SUN为单位的金额（最小单位）
     * @param string|null $trx 以TRX为单位的金额（可选，用于缓存）
     */
    public function __construct(
        public readonly string $sun,
        private ?string $trx = null,
    ) {
        $this->validate();
    }

    /**
     * 从SUN创建实例
     */
    public static function fromSun(string $sun): self
    {
        return new self($sun);
    }

    /**
     * 从TRX创建实例
     */
    public static function fromTrx(string $trx): self
    {
        // 将TRX转换为SUN
        $sun = BigDecimal::of($trx)
            ->multipliedBy(self::SUN_PER_TRX)
            ->toScale(0, RoundingMode::DOWN)
            ->__toString()
        ;

        // 格式化TRX以保持一致的小数位数
        $formattedTrx = BigDecimal::of($trx)
            ->toScale(self::DECIMALS, RoundingMode::DOWN)
            ->__toString()
        ;

        return new self($sun, $formattedTrx);
    }

    /**
     * 从整数SUN创建实例
     */
    public static function fromSunInt(int $sun): self
    {
        return new self((string) $sun);
    }

    /**
     * 从浮点数TRX创建实例（注意：大额可能损失精度）
     */
    public static function fromTrxFloat(float $trx): self
    {
        return self::fromTrx((string) $trx);
    }

    /**
     * 获取SUN金额
     */
    public function getSun(): string
    {
        return $this->sun;
    }

    /**
     * 获取SUN金额（整数形式）
     */
    public function getSunInt(): int
    {
        return (int) $this->sun;
    }

    /**
     * 获取TRX金额
     */
    public function getTrx(): string
    {
        if (null !== $this->trx) {
            return $this->trx;
        }

        $this->trx = $this->calculateTrx();

        return $this->trx;
    }

    /**
     * 获取TRX金额（浮点数形式，注意：大额可能损失精度）
     */
    public function getTrxFloat(): float
    {
        return (float) $this->getTrx();
    }

    /**
     * 检查金额是否为零
     */
    public function isZero(): bool
    {
        return '0' === $this->sun;
    }

    /**
     * 检查金额是否为正
     */
    public function isPositive(): bool
    {
        return BigDecimal::of($this->sun)->isPositive();
    }

    /**
     * 检查金额是否为负
     */
    public function isNegative(): bool
    {
        return BigDecimal::of($this->sun)->isNegative();
    }

    /**
     * 比较两个金额
     *
     * @return int -1 if less than, 0 if equal, 1 if greater than
     */
    public function compareTo(TronAmount $other): int
    {
        return BigDecimal::of($this->sun)->compareTo(BigDecimal::of($other->sun));
    }

    /**
     * 加法
     */
    public function add(TronAmount $other): self
    {
        $resultSun = BigDecimal::of($this->sun)
            ->plus(BigDecimal::of($other->sun))
            ->__toString()
        ;

        return new self($resultSun);
    }

    /**
     * 减法
     */
    public function subtract(TronAmount $other): self
    {
        $resultSun = BigDecimal::of($this->sun)
            ->minus(BigDecimal::of($other->sun))
            ->__toString()
        ;

        return new self($resultSun);
    }

    /**
     * 乘法（乘以一个系数）
     */
    public function multiply(string $multiplier): self
    {
        $resultSun = BigDecimal::of($this->sun)
            ->multipliedBy($multiplier)
            ->toScale(0, RoundingMode::DOWN)
            ->__toString()
        ;

        return new self($resultSun);
    }

    /**
     * 除法（除以一个除数）
     */
    public function divide(string $divisor): self
    {
        if ('0' === $divisor) {
            throw new InvalidArgumentException('Cannot divide by zero');
        }

        $resultSun = BigDecimal::of($this->sun)
            ->dividedBy($divisor, 0, RoundingMode::DOWN)
            ->__toString()
        ;

        return new self($resultSun);
    }

    /**
     * 格式化为字符串（TRX单位）
     */
    public function format(bool $withSymbol = true): string
    {
        $trx = $this->getTrx();

        return $withSymbol ? "{$trx} TRX" : $trx;
    }

    /**
     * 转换为数组格式（向后兼容）
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sun' => $this->sun,
            'trx' => $this->getTrx(),
            'decimals' => self::DECIMALS,
        ];
    }

    /**
     * 返回字符串表示（TRX单位）
     */
    public function __toString(): string
    {
        return $this->getTrx();
    }

    private function validate(): void
    {
        // 验证SUN是有效的数字字符串（可以是负数）
        if (!preg_match('/^-?[0-9]+$/', $this->sun)) {
            throw new InvalidArgumentException('SUN amount must be a numeric string');
        }
    }

    private function calculateTrx(): string
    {
        return BigDecimal::of($this->sun)
            ->dividedBy(self::SUN_PER_TRX, self::DECIMALS, RoundingMode::DOWN)
            ->toScale(self::DECIMALS, RoundingMode::DOWN)
            ->__toString();
    }
}
