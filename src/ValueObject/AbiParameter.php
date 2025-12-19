<?php

declare(strict_types=1);

namespace Tourze\TronAPI\ValueObject;

use Tourze\TronAPI\Exception\InvalidArgumentException;

/**
 * Value Object representing an ABI function parameter
 * Encapsulates parameter type information for smart contract calls
 */
final readonly class AbiParameter
{
    /**
     * @param string $type Parameter type (e.g., 'address', 'uint256', 'string')
     * @param string|null $name Optional parameter name
     * @param bool $indexed Whether parameter is indexed (for events)
     */
    public function __construct(
        public string $type,
        public ?string $name = null,
        public bool $indexed = false,
    ) {
        if ('' === trim($this->type)) {
            throw new InvalidArgumentException('ABI parameter type cannot be empty');
        }
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
        if (!isset($data['type']) || !is_string($data['type'])) {
            throw new InvalidArgumentException('ABI parameter must have a string type field');
        }

        $name = null;
        if (isset($data['name']) && is_string($data['name'])) {
            $name = $data['name'];
        }

        $indexed = false;
        if (isset($data['indexed'])) {
            $indexed = (bool) $data['indexed'];
        }

        return new self(
            type: $data['type'],
            name: $name,
            indexed: $indexed
        );
    }

    /**
     * 转换为 Ethabi 期望的数组格式
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = ['type' => $this->type];

        if (null !== $this->name) {
            $result['name'] = $this->name;
        }

        if ($this->indexed) {
            $result['indexed'] = true;
        }

        return $result;
    }

    /**
     * 获取参数类型
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * 获取参数名称（如果可用）
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * 检查参数是否被索引
     */
    public function isIndexed(): bool
    {
        return $this->indexed;
    }
}
