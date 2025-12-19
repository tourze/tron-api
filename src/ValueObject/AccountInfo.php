<?php

declare(strict_types=1);

namespace Tourze\TronAPI\ValueObject;

use Tourze\TronAPI\Exception\InvalidArgumentException;
use Tourze\TronAPI\Exception\RuntimeException;

/**
 * Account 信息值对象
 * 封装 TRON API getAccount 响应
 */
readonly class AccountInfo
{
    /**
     * @param string $address 账户地址（hex格式）
     * @param int $balance 账户余额（最小单位 sun）
     * @param array<int, array{key: string, value: int|string}> $assetV2 TRC10 资产列表
     * @param array<string, mixed> $rawData 原始响应数据（包含所有其他字段）
     */
    private function __construct(
        private string $address,
        private int $balance,
        private array $assetV2,
        private array $rawData,
    ) {
    }

    /**
     * 从 API 响应数组创建 AccountInfo
     *
     * @param array<string, mixed> $data
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::extractAddress($data),
            self::extractBalance($data),
            self::parseAssetV2($data),
            $data
        );
    }

    /**
     * 提取账户地址
     *
     * @param array<string, mixed> $data
     * @throws InvalidArgumentException
     */
    private static function extractAddress(array $data): string
    {
        if (!isset($data['address'])) {
            return '';
        }

        if (!is_string($data['address'])) {
            throw new InvalidArgumentException('Account address must be a string');
        }

        return $data['address'];
    }

    /**
     * 提取账户余额
     *
     * @param array<string, mixed> $data
     * @throws InvalidArgumentException
     */
    private static function extractBalance(array $data): int
    {
        if (!isset($data['balance'])) {
            return 0;
        }

        if (!is_numeric($data['balance'])) {
            throw new InvalidArgumentException('Account balance must be numeric');
        }

        return (int) $data['balance'];
    }

    /**
     * 解析 TRC10 资产列表
     *
     * @param array<string, mixed> $data
     * @return array<int, array{key: string, value: int|string}>
     * @throws InvalidArgumentException
     */
    private static function parseAssetV2(array $data): array
    {
        if (!isset($data['assetV2'])) {
            return [];
        }

        if (!is_array($data['assetV2'])) {
            throw new InvalidArgumentException('Account assetV2 must be an array');
        }

        $assets = [];
        foreach ($data['assetV2'] as $asset) {
            $normalized = self::normalizeAsset($asset);
            if (null !== $normalized) {
                $assets[] = $normalized;
            }
        }

        return $assets;
    }

    /**
     * 标准化单个资产结构
     *
     * @param mixed $asset
     * @return array{key: string, value: int|string}|null
     * @throws RuntimeException
     */
    private static function normalizeAsset(mixed $asset): ?array
    {
        if (!is_array($asset)) {
            return null;
        }

        if (!isset($asset['key'])) {
            return null;
        }

        if (!isset($asset['value'])) {
            throw new RuntimeException('Invalid token value structure');
        }

        if (!is_string($asset['key']) && !is_numeric($asset['key'])) {
            return null;
        }

        if (!is_numeric($asset['value'])) {
            return null;
        }

        return [
            'key' => (string) $asset['key'],
            'value' => $asset['value'],
        ];
    }

    /**
     * 获取账户地址
     */
    public function getAddress(): string
    {
        return $this->address;
    }

    /**
     * 获取账户余额（sun 单位）
     */
    public function getBalance(): int
    {
        return $this->balance;
    }

    /**
     * 获取 TRC10 资产列表
     *
     * @return array<int, array{key: string, value: int|string}>
     */
    public function getAssetV2(): array
    {
        return $this->assetV2;
    }

    /**
     * 判断账户是否为空（余额为0且无资产）
     */
    public function isEmpty(): bool
    {
        return 0 === $this->balance && 0 === count($this->assetV2);
    }

    /**
     * 查找指定 tokenId 的资产余额
     *
     * @return int|string|null 返回余额，如果不存在返回 null
     */
    public function findAssetBalance(int $tokenId): int|string|null
    {
        $tokenIdStr = (string) $tokenId;

        foreach ($this->assetV2 as $asset) {
            if ($asset['key'] === $tokenIdStr) {
                return $asset['value'];
            }
        }

        return null;
    }

    /**
     * 获取原始响应中的特定字段
     *
     * @param string $key
     * @return mixed
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
