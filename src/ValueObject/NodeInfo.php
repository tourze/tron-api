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
        $host = '';
        $port = 0;

        if (isset($data['address']) && is_array($data['address'])) {
            $addressData = $data['address'];

            if (isset($addressData['host'])) {
                $hostValue = $addressData['host'];
                if (is_string($hostValue)) {
                    $host = $hostValue;
                } elseif (is_scalar($hostValue)) {
                    $host = (string) $hostValue;
                }
            }

            if (isset($addressData['port'])) {
                $portValue = $addressData['port'];
                if (is_int($portValue)) {
                    $port = $portValue;
                } elseif (is_numeric($portValue)) {
                    $port = (int) $portValue;
                }
            }
        }

        $address = '' !== $host && $port > 0 ? "{$host}:{$port}" : '';

        return new self($host, $port, $address);
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
