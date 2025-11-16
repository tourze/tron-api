<?php

declare(strict_types=1);

namespace Tourze\TronAPI\ValueObject;

use Tourze\TronAPI\Exception\InvalidArgumentException;

/**
 * Value Object representing an ABI function definition
 * Encapsulates complete function metadata for smart contract interactions
 */
final readonly class AbiFunction
{
    /**
     * @param string $name Function name
     * @param list<AbiParameter> $inputs Input parameters
     * @param list<AbiParameter> $outputs Output parameters
     * @param string $type Function type (function, constructor, fallback, receive)
     * @param string|null $stateMutability State mutability (pure, view, nonpayable, payable)
     */
    public function __construct(
        public string $name,
        public array $inputs,
        public array $outputs,
        public string $type = 'function',
        public ?string $stateMutability = null,
    ) {
        if ('' === trim($this->name) && 'constructor' !== $this->type && 'fallback' !== $this->type) {
            throw new InvalidArgumentException('ABI function name cannot be empty');
        }
        // Note: PHPDoc list<AbiParameter> ensures type safety at static analysis time
    }

    /**
     * 从 ABI 数组结构创建实例
     *
     * @param array<string, mixed> $data
     * @return self
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: self::extractName($data),
            inputs: self::parseParameters($data, 'inputs'),
            outputs: self::parseParameters($data, 'outputs'),
            type: self::extractType($data),
            stateMutability: self::extractStateMutability($data)
        );
    }

    /**
     * 提取函数名称
     *
     * @param array<string, mixed> $data
     */
    private static function extractName(array $data): string
    {
        if (isset($data['name']) && is_string($data['name'])) {
            return $data['name'];
        }

        return '';
    }

    /**
     * 提取函数类型
     *
     * @param array<string, mixed> $data
     */
    private static function extractType(array $data): string
    {
        if (isset($data['type']) && is_string($data['type'])) {
            return $data['type'];
        }

        return 'function';
    }

    /**
     * 提取状态可变性
     *
     * @param array<string, mixed> $data
     */
    private static function extractStateMutability(array $data): ?string
    {
        if (isset($data['stateMutability']) && is_string($data['stateMutability'])) {
            return $data['stateMutability'];
        }

        if (isset($data['constant']) && true === $data['constant']) {
            return 'view';
        }

        if (isset($data['payable']) && true === $data['payable']) {
            return 'payable';
        }

        return null;
    }

    /**
     * 解析参数列表（inputs 或 outputs）
     *
     * @param array<string, mixed> $data
     * @param string $key 'inputs' 或 'outputs'
     * @return list<AbiParameter>
     * @throws InvalidArgumentException
     */
    private static function parseParameters(array $data, string $key): array
    {
        if (!isset($data[$key]) || !is_array($data[$key])) {
            return [];
        }

        $parameters = [];
        foreach ($data[$key] as $paramData) {
            if (!is_array($paramData)) {
                throw new InvalidArgumentException("Each {$key} must be an array");
            }
            $parameters[] = AbiParameter::fromArray(self::normalizeArrayKeys($paramData));
        }

        return $parameters;
    }

    /**
     * 规范化数组键为字符串
     *
     * @param array<mixed, mixed> $data
     * @return array<string, mixed>
     */
    private static function normalizeArrayKeys(array $data): array
    {
        $normalized = [];
        foreach ($data as $k => $v) {
            $normalized[is_string($k) ? $k : (string) $k] = $v;
        }

        return $normalized;
    }

    /**
     * 转换为 Ethabi 期望的数组格式
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'name' => $this->name,
            'type' => $this->type,
            'inputs' => array_map(fn (AbiParameter $p) => $p->toArray(), $this->inputs),
            'outputs' => array_map(fn (AbiParameter $p) => $p->toArray(), $this->outputs),
        ];

        if (null !== $this->stateMutability) {
            $result['stateMutability'] = $this->stateMutability;
        }

        return $result;
    }

    /**
     * Build function signature (e.g., "transfer(address,uint256)")
     */
    public function buildSignature(): string
    {
        $types = array_map(fn (AbiParameter $p) => $p->getType(), $this->inputs);

        return $this->name . '(' . implode(',', $types) . ')';
    }

    /**
     * 获取函数名称
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * 获取输入参数
     *
     * @return list<AbiParameter>
     */
    public function getInputs(): array
    {
        return $this->inputs;
    }

    /**
     * 获取输出参数
     *
     * @return list<AbiParameter>
     */
    public function getOutputs(): array
    {
        return $this->outputs;
    }

    /**
     * 获取输入参数数量
     */
    public function getInputCount(): int
    {
        return count($this->inputs);
    }

    /**
     * 获取输出参数数量
     */
    public function getOutputCount(): int
    {
        return count($this->outputs);
    }

    /**
     * 检查函数是否为 view/constant
     */
    public function isView(): bool
    {
        return in_array($this->stateMutability, ['view', 'pure'], true);
    }

    /**
     * 检查函数是否可支付
     */
    public function isPayable(): bool
    {
        return 'payable' === $this->stateMutability;
    }

    /**
     * 检查函数是否有输出
     */
    public function hasOutputs(): bool
    {
        return count($this->outputs) > 0;
    }
}
