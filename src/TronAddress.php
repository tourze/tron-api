<?php

namespace Tourze\TronAPI;

use Tourze\TronAPI\Exception\InvalidArgumentException;

class TronAddress
{
    /**
     * 地址生成结果
     *
     * @var array{
     *     private_key: string,
     *     public_key: string,
     *     address_hex: string,
     *     address_base58: string
     * }
     */
    protected array $response;

    /**
     * 构造函数
     *
     * @param array{
     *     private_key: string,
     *     public_key: string,
     *     address_hex: string,
     *     address_base58: string
     * } $data
     * @throws InvalidArgumentException
     */
    public function __construct(array $data)
    {
        // 验证必需的键
        if (!$this->array_keys_exist($data, ['address_hex', 'private_key', 'public_key'])) {
            throw new InvalidArgumentException('Incorrectly generated address');
        }

        // 验证所有值都是字符串
        foreach (['private_key', 'public_key', 'address_hex', 'address_base58'] as $key) {
            if (isset($data[$key]) && !is_string($data[$key])) {
                throw new InvalidArgumentException("Address field '{$key}' must be a string");
            }
        }

        $this->response = $data;
    }

    /**
     * 获取地址
     *
     * @param bool $is_base58 是否返回Base58格式（默认返回十六进制格式）
     * @return string 地址字符串
     */
    public function getAddress(bool $is_base58 = false): string
    {
        return $this->response[$is_base58 ? 'address_base58' : 'address_hex'];
    }

    /**
     * 获取公钥
     *
     * @return string 公钥（十六进制格式）
     */
    public function getPublicKey(): string
    {
        return $this->response['public_key'];
    }

    /**
     * 获取私钥
     *
     * @return string 私钥（十六进制格式）
     */
    public function getPrivateKey(): string
    {
        return $this->response['private_key'];
    }

    /**
     * 获取原始数据数组
     *
     * @return array{
     *     private_key: string,
     *     public_key: string,
     *     address_hex: string,
     *     address_base58: string
     * }
     */
    public function getRawData(): array
    {
        return $this->response;
    }

    /**
     * 检查数组中是否存在多个键
     *
     * @param array<string, mixed> $array
     * @param array<int, string> $keys
     */
    private function array_keys_exist(array $array, array $keys = []): bool
    {
        $count = 0;
        foreach ($keys as $key) {
            if (isset($array[$key]) || array_key_exists($key, $array)) {
                ++$count;
            }
        }

        return count($keys) === $count;
    }
}
