<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Support;

use Tourze\TronAPI\Exception\InvalidArgumentException;
use Tourze\TronAPI\Exception\RuntimeException;

class BigInteger
{
    /**
     * 表示为 GMP 资源的值
     *
     * @var \GMP
     */
    private $value;

    /**
     * 表示此对象的状态是否可以更改的标志
     *
     * @var bool
     */
    private $mutable;

    /**
     * 初始化此类的新实例
     *
     * @param string $value   要设置的值
     * @param bool   $mutable 此对象的状态是否可以更改
     */
    public function __construct(string $value = '0', bool $mutable = true)
    {
        $this->value = $this->initValue($value);
        $this->mutable = $mutable;
    }

    /**
     * 获取大整数的值
     */
    public function getValue(): string
    {
        return gmp_strval($this->value);
    }

    /**
     * 设置值
     *
     * @param string $value 要设置的值
     */
    public function setValue(string $value): BigInteger
    {
        if (!$this->isMutable()) {
            throw new RuntimeException('Cannot set the value since the number is immutable.');
        }

        $this->value = $this->initValue($value);

        return $this;
    }

    /**
     * 将值转换为绝对数
     */
    public function abs(): BigInteger
    {
        $value = gmp_abs($this->value);

        return $this->assignValue($value);
    }

    /**
     * 将给定值添加到此值
     *
     * @param string $value 要添加的值
     */
    public function add(string $value): BigInteger
    {
        $gmp = $this->initValue($value);

        $calculatedValue = gmp_add($this->value, $gmp);

        return $this->assignValue($calculatedValue);
    }

    /**
     * 比较此数字和给定数字
     *
     * @param string $value 要比较的值
     *
     * @return int 如果数字小于此数字则返回 -1，相等返回 0，大于返回 1
     */
    public function cmp($value): int
    {
        $value = $this->initValue($value);

        $result = gmp_cmp($this->value, $value);

        // It could happen that gmp_cmp returns a value greater than one (e.g. gmp_cmp('123', '-123')). That's why
        // we do an additional check to make sure to return the correct value.

        if ($result > 0) {
            return 1;
        }
        if ($result < 0) {
            return -1;
        }

        return 0;
    }

    /**
     * 将此值除以给定值
     *
     * @param string $value 要除以的值
     */
    public function divide(string $value): BigInteger
    {
        $gmp = $this->initValue($value);

        $calculatedValue = gmp_div_q($this->value, $gmp, GMP_ROUND_ZERO);

        return $this->assignValue($calculatedValue);
    }

    /**
     * 计算此值的阶乘
     */
    public function factorial(): BigInteger
    {
        $calculatedValue = gmp_fact($this->getValue());

        return $this->assignValue($calculatedValue);
    }

    /**
     * 对给定数字执行模运算
     *
     * @param string $value 要执行模运算的值
     */
    public function mod(string $value): BigInteger
    {
        $gmp = $this->initValue($value);

        $calculatedValue = gmp_mod($this->value, $gmp);

        return $this->assignValue($calculatedValue);
    }

    /**
     * 将给定值与此值相乘
     *
     * @param string $value 要相乘的值
     */
    public function multiply(string $value): BigInteger
    {
        $gmp = $this->initValue($value);

        $calculatedValue = gmp_mul($this->value, $gmp);

        return $this->assignValue($calculatedValue);
    }

    /**
     * 对值进行取反操作
     */
    public function negate(): BigInteger
    {
        $calculatedValue = gmp_neg($this->value);

        return $this->assignValue($calculatedValue);
    }

    /**
     * 对给定数字执行幂运算
     *
     * @param int $value 要执行幂运算的值
     */
    public function pow(int $value): BigInteger
    {
        $calculatedValue = gmp_pow($this->value, $value);

        return $this->assignValue($calculatedValue);
    }

    /**
     * 从此值中减去给定值
     *
     * @param string $value 要减去的值
     */
    public function subtract(string $value): BigInteger
    {
        $gmp = $this->initValue($value);

        $calculatedValue = gmp_sub($this->value, $gmp);

        return $this->assignValue($calculatedValue);
    }

    /**
     * 检查大整数是否为质数
     *
     * @param float $probabilityFactor 用于检查概率的 0 到 1 之间的归一化因子
     *
     * @return bool 如果是质数则返回 true，否则返回 false
     */
    public function isPrimeNumber(float $probabilityFactor = 1.0): bool
    {
        $reps = (int) floor(($probabilityFactor * 5.0) + 5.0);

        if ($reps < 5 || $reps > 10) {
            throw new InvalidArgumentException('The provided probability number should be 5 to 10.');
        }

        return 0 !== gmp_prob_prime($this->value, $reps);
    }

    /**
     * 检查此对象是否可变
     */
    public function isMutable(): bool
    {
        return $this->mutable;
    }

    /**
     * 将此类转换为字符串
     */
    public function toString(): string
    {
        return $this->getValue();
    }

    /**
     * 将此类转换为字符串
     */
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * 用于分配给定值的辅助方法
     *
     * @param \GMP $value 要分配的值
     */
    private function assignValue(\GMP $value): BigInteger
    {
        $rawValue = gmp_strval($value);

        if ($this->isMutable()) {
            $this->value = gmp_init($rawValue);

            return $this;
        }

        return new BigInteger($rawValue, false);
    }

    /**
     * 创建新的 GMP 对象
     *
     * @param string $value 要初始化的值
     *
     * @throws InvalidArgumentException 当值无效时抛出
     */
    private function initValue(string $value): \GMP
    {
        try {
            return gmp_init($value);
        } catch (\Exception $e) {
            throw new InvalidArgumentException('The provided number is invalid.');
        }
    }
}
