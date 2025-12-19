<?php

declare(strict_types=1);

namespace Tourze\TronAPI\ValueObject;

/**
 * 节点信息值对象
 * 封装 TRON 网络节点信息
 */
class NodeInfo
{
    /**
     * @param string $host 节点主机地址
     * @param int $port 节点端口
     * @param string $address 节点完整地址（host:port）
     */
    private function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $address,
    ) {
    }

    /**
     * 从节点地址字符串创建 NodeInfo
     *
     * @param string $address 格式："host:port"
     */
    public static function fromAddress(string $address): self
    {
        $parts = explode(':', $address);
        $host = $parts[0] ?? '';
        $port = isset($parts[1]) && is_numeric($parts[1]) ? (int) $parts[1] : 0;

        return new self($host, $port, $address);
    }

    /**
     * 从 API 响应数组创建 NodeInfo
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $addressData = $data['address'] ?? null;
        if (!is_array($addressData)) {
            $addressData = [];
        }

        $host = self::extractHost($addressData);
        $port = self::extractPort($addressData);
        $address = self::buildAddress($host, $port);

        return new self($host, $port, $address);
    }

    /**
     * 从地址数据中提取 host
     *
     * @param array<string, mixed> $addressData
     */
    private static function extractHost(array $addressData): string
    {
        if (!isset($addressData['host'])) {
            return '';
        }

        /** @var mixed $hostValue */
        $hostValue = $addressData['host'];

        if (is_string($hostValue)) {
            return $hostValue;
        }

        if (is_scalar($hostValue)) {
            return (string) $hostValue;
        }

        return '';
    }

    /**
     * 从地址数据中提取 port
     *
     * @param array<string, mixed> $addressData
     */
    private static function extractPort(array $addressData): int
    {
        if (!isset($addressData['port'])) {
            return 0;
        }

        /** @var mixed $portValue */
        $portValue = $addressData['port'];

        if (is_int($portValue)) {
            return $portValue;
        }

        if (is_numeric($portValue)) {
            return (int) $portValue;
        }

        return 0;
    }

    /**
     * 构建完整地址字符串
     */
    private static function buildAddress(string $host, int $port): string
    {
        if ('' !== $host && $port > 0) {
            return "{$host}:{$port}";
        }

        return '';
    }

    /**
     * 获取节点主机地址
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * 获取节点端口
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * 获取节点完整地址
     */
    public function getAddress(): string
    {
        return $this->address;
    }

    /**
     * 判断节点信息是否有效
     */
    public function isValid(): bool
    {
        return '' !== $this->host && $this->port > 0;
    }

    /**
     * 转换为字符串
     */
    public function __toString(): string
    {
        return $this->address;
    }

    /**
     * 转换为数组
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'host' => $this->host,
            'port' => $this->port,
            'address' => $this->address,
        ];
    }
}
