<?php

declare(strict_types=1);

namespace Tourze\TronAPI\ValueObject;

use Tourze\TronAPI\Exception\InvalidArgumentException;

/**
 * 地址验证结果值对象
 * 封装 TRON API validateAddress 响应
 */
class AddressValidation
{
    /**
     * @param bool $result 验证结果
     * @param string $message 验证消息
     * @param array<string, mixed> $rawData 原始响应数据
     */
    private function __construct(
        private readonly bool $result,
        private readonly string $message,
        private readonly array $rawData,
    ) {
    }

    /**
     * 从 API 响应数组创建 AddressValidation
     *
     * @param array<string, mixed> $data
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        // result 字段必须存在
        if (!isset($data['result'])) {
            throw new InvalidArgumentException('Validation result is required');
        }
        $result = (bool) $data['result'];

        // message 字段可选，默认为空字符串
        $message = '';
        if (isset($data['message']) && is_string($data['message'])) {
            $message = $data['message'];
        }

        return new self(
            $result,
            $message,
            $data
        );
    }

    /**
     * 获取验证结果
     */
    public function isValid(): bool
    {
        return $this->result;
    }

    /**
     * 获取验证消息
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * 获取原始响应中的特定字段
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
