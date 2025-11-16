<?php

declare(strict_types=1);

namespace Tourze\TronAPI\ValueObject;

use Tourze\TronAPI\Exception\InvalidArgumentException;

/**
 * Token创建配置选项值对象
 */
class TokenOptions
{
    public function __construct(
        public readonly string $name,
        public readonly string $abbreviation,
        public readonly int $totalSupply,
        public readonly int $trxRatio,
        public readonly int $saleStart,
        public readonly int $saleEnd,
        public readonly string $description,
        public readonly string $url,
        public readonly int $freeBandwidth = 0,
        public readonly int $freeBandwidthLimit = 0,
        public readonly int $frozenAmount = 0,
        public readonly int $frozenDuration = 0,
    ) {
        $this->validate();
    }

    /**
     * 从数组创建TokenOptions实例
     *
     * @param array<string, mixed> $options
     */
    public static function fromArray(array $options, int $startTimeStamp): self
    {
        $instance = new self(
            name: self::ensureString($options['name'] ?? ''),
            abbreviation: self::ensureString($options['abbreviation'] ?? ''),
            totalSupply: self::ensureInt($options['totalSupply'] ?? 0),
            trxRatio: self::ensureInt($options['trxRatio'] ?? 0),
            saleStart: self::ensureInt($options['saleStart'] ?? 0),
            saleEnd: self::ensureInt($options['saleEnd'] ?? 0),
            description: self::ensureString($options['description'] ?? ''),
            url: self::ensureString($options['url'] ?? ''),
            freeBandwidth: self::ensureInt($options['freeBandwidth'] ?? 0),
            freeBandwidthLimit: self::ensureInt($options['freeBandwidthLimit'] ?? 0),
            frozenAmount: self::ensureInt($options['frozenAmount'] ?? 0),
            frozenDuration: self::ensureInt($options['frozenDuration'] ?? 0),
        );

        // 验证起始时间约束
        if ($instance->saleStart <= $startTimeStamp) {
            throw new InvalidArgumentException('Invalid sale start timestamp provided');
        }

        return $instance;
    }

    /**
     * 转换为数组格式
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'abbreviation' => $this->abbreviation,
            'totalSupply' => $this->totalSupply,
            'trxRatio' => $this->trxRatio,
            'saleStart' => $this->saleStart,
            'saleEnd' => $this->saleEnd,
            'description' => $this->description,
            'url' => $this->url,
            'freeBandwidth' => $this->freeBandwidth,
            'freeBandwidthLimit' => $this->freeBandwidthLimit,
            'frozenAmount' => $this->frozenAmount,
            'frozenDuration' => $this->frozenDuration,
        ];
    }

    private function validate(): void
    {
        $this->validateName()
            ->validateAbbreviation()
            ->validateSupply()
            ->validateRatio()
            ->validateSaleTimeWindow()
            ->validateDescription()
            ->validateUrl()
            ->validateBandwidth()
            ->validateFrozenSettings()
        ;
    }

    private function validateName(): self
    {
        if ('' === $this->name) {
            throw new InvalidArgumentException('Invalid token name provided');
        }

        return $this;
    }

    private function validateAbbreviation(): self
    {
        if ('' === $this->abbreviation) {
            throw new InvalidArgumentException('Invalid token abbreviation provided');
        }

        return $this;
    }

    private function validateSupply(): self
    {
        if ($this->totalSupply <= 0) {
            throw new InvalidArgumentException('Invalid supply amount provided');
        }

        return $this;
    }

    private function validateRatio(): self
    {
        if ($this->trxRatio <= 0) {
            throw new InvalidArgumentException('TRX ratio must be a positive integer');
        }

        return $this;
    }

    private function validateSaleTimeWindow(): self
    {
        if ($this->saleStart <= 0) {
            throw new InvalidArgumentException('Invalid sale start timestamp provided');
        }

        if ($this->saleEnd <= $this->saleStart) {
            throw new InvalidArgumentException('Invalid sale end timestamp provided');
        }

        return $this;
    }

    private function validateDescription(): self
    {
        if ('' === $this->description) {
            throw new InvalidArgumentException('Invalid token description provided');
        }

        return $this;
    }

    private function validateUrl(): self
    {
        $validatedUrl = filter_var($this->url, FILTER_VALIDATE_URL);
        if (false === $validatedUrl) {
            throw new InvalidArgumentException('Invalid token url provided');
        }

        return $this;
    }

    private function validateBandwidth(): self
    {
        if ($this->freeBandwidth < 0) {
            throw new InvalidArgumentException('Invalid free bandwidth amount provided');
        }

        if ($this->freeBandwidthLimit < 0 || ($this->freeBandwidth > 0 && 0 === $this->freeBandwidthLimit)) {
            throw new InvalidArgumentException('Invalid free bandwidth limit provided');
        }

        return $this;
    }

    /**
     * 确保值为字符串类型
     */
    private static function ensureString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_null($value) || is_scalar($value)) {
            return (string) $value;
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

    private function validateFrozenSettings(): self
    {
        if ($this->frozenAmount < 0 || (0 === $this->frozenDuration && 0 !== $this->frozenAmount)) {
            throw new InvalidArgumentException('Invalid frozen supply provided');
        }

        return $this;
    }
}
