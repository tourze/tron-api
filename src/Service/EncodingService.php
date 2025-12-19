<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Service;

use Tourze\TronAPI\Exception\InvalidArgumentException;
use Tourze\TronAPI\ValueObject\TronAmount;

/**
 * 编码服务类
 * 负责处理各种编码转换操作
 */
class EncodingService
{
    /**
     * 转换为十六进制
     */
    public function toHex(string $string): string
    {
        return '0x' . bin2hex($string);
    }

    /**
     * 从十六进制转换
     */
    public function fromHex(string $hexString): string
    {
        $hex = str_replace('0x', '', $hexString);

        // 验证是否为有效的十六进制字符串
        if (!$this->isValidHex($hex)) {
            return '';
        }

        $result = hex2bin($hex);

        return false !== $result ? $result : '';
    }

    /**
     * UTF-8字符串转十六进制
     */
    public function stringUtf8toHex(string $string): string
    {
        return bin2hex($string);
    }

    /**
     * 十六进制转UTF-8字符串
     */
    public function hexString2Utf8(string $hexString): string
    {
        // 验证是否为有效的十六进制字符串
        if (!$this->isValidHex($hexString)) {
            return '';
        }

        $string = hex2bin($hexString);

        return false !== $string ? $string : '';
    }

    /**
     * 转换TRX为SUN（类型安全版本）
     */
    public function trxToSun(string $trx): TronAmount
    {
        return TronAmount::fromTrx($trx);
    }

    /**
     * 转换SUN为TRX（类型安全版本）
     */
    public function sunToTrx(string $sun): TronAmount
    {
        return TronAmount::fromSun($sun);
    }

    /**
     * 地址转换为十六进制字符串
     *
     * @throws InvalidArgumentException
     */
    public function address2HexString(?string $address): string
    {
        if (null === $address || '' === $address) {
            throw new InvalidArgumentException('Address cannot be null or empty');
        }

        if ($this->isHexAddress($address)) {
            return $address;
        }

        // Base58检查地址解码逻辑
        return $this->base58CheckDecode($address);
    }

    /**
     * 验证是否为有效的十六进制字符串
     */
    private function isValidHex(string $hex): bool
    {
        // 空字符串视为有效（hex2bin('')返回''）
        if ('' === $hex) {
            return true;
        }

        // 十六进制字符串长度必须为偶数
        if (0 !== strlen($hex) % 2) {
            return false;
        }

        // 检查是否只包含有效的十六进制字符
        return 1 === preg_match('/^[0-9a-fA-F]+$/', $hex);
    }

    /**
     * 检查是否为十六进制地址
     */
    private function isHexAddress(string $address): bool
    {
        return 42 === strlen($address) && '41' === substr($address, 0, 2);
    }

    /**
     * Base58检查解码
     *
     * @throws InvalidArgumentException
     */
    private function base58CheckDecode(string $address): string
    {
        // 这里应该实现实际的Base58检查解码
        // 为简化起见，返回占位符
        if (34 !== strlen($address)) {
            throw new InvalidArgumentException('Invalid address length');
        }

        // 实际实现应该进行Base58解码和校验和检查
        return '41' . str_repeat('0', 40); // 占位符
    }
}
