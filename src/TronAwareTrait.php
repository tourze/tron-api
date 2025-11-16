<?php

declare(strict_types=1);

namespace Tourze\TronAPI;

use Tourze\TronAPI\Support\Base58Check;
use Tourze\TronAPI\Support\BigInteger;
use Tourze\TronAPI\Support\Keccak;

trait TronAwareTrait
{
    /**
     * 从十六进制转换
     *
     * @param string $string
     * @return string|null
     */
    public function fromHex(string $string): ?string
    {
        if (42 === strlen($string) && '41' === mb_substr($string, 0, 2)) {
            return $this->hexString2Address($string);
        }

        return $this->hexString2Utf8($string);
    }

    /**
     * 转换为十六进制
     *
     * @param string $str
     * @return string
     */
    public function toHex(string $str): string
    {
        if (34 === mb_strlen($str) && 'T' === mb_substr($str, 0, 1)) {
            return $this->address2HexString($str);
        }

        return $this->stringUtf8toHex($str);
    }

    /**
     * 在转换为十六进制之前检查地址
     *
     * @param string $sHexAddress
     * @return string
     */
    public function address2HexString(string $sHexAddress): string
    {
        if (42 === strlen($sHexAddress) && 0 === mb_strpos($sHexAddress, '41')) {
            return $sHexAddress;
        }

        return Base58Check::decode($sHexAddress, 0, 3);
    }

    /**
     * 在转换为 Base58 之前检查十六进制地址
     *
     * @param string $sHexString
     * @return string
     */
    public function hexString2Address(string $sHexString): string
    {
        if (!ctype_xdigit($sHexString)) {
            return $sHexString;
        }

        if (strlen($sHexString) < 2 || (strlen($sHexString) & 1) !== 0) {
            return '';
        }

        return Base58Check::encode($sHexString, 0, false);
    }

    /**
     * 将字符串转换为十六进制
     *
     * @param string $sUtf8
     * @return string
     */
    public function stringUtf8toHex(string $sUtf8): string
    {
        return bin2hex($sUtf8);
    }

    /**
     * 将十六进制转换为字符串
     *
     * @param string $sHexString
     * @return string|null
     */
    public function hexString2Utf8(string $sHexString): ?string
    {
        $result = hex2bin($sHexString);

        return (false !== $result) ? $result : null;
    }

    /**
     * 转换为大整数值
     *
     * @param string|int|float $str
     * @return BigInteger
     */
    public function toBigNumber(string|int|float $str): BigInteger
    {
        return new BigInteger((string) $str);
    }

    /**
     * 将 TRX 转换为浮点数
     * @param string|int|float $amount
     */
    public function fromTron(string|int|float $amount): float
    {
        return (float) bcdiv((string) $amount, (string) 1e6, 8);
    }

    /**
     * 将浮点数转换为 TRX 格式
     * @param string|int|float $double
     */
    public function toTron(string|int|float $double): int
    {
        /** @var numeric-string $doubleStr */
        $doubleStr = (string) $double;

        return (int) bcmul($doubleStr, (string) 1e6, 0);
    }

    /**
     * 转换为 SHA3
     *
     * @param string $string
     * @param bool $prefix
     *
     * @return string
     *
     * @throws \Exception
     */
    public function sha3(string $string, bool $prefix = true): string
    {
        return ($prefix ? '0x' : '') . Keccak::hash($string, 256);
    }
}
