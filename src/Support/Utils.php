<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Support;

use Tourze\TronAPI\Exception\InvalidArgumentException;
use Tourze\TronAPI\Exception\ValidationException;

class Utils
{
    /**
     * 链接验证
     * @param string $url
     */
    public static function isValidUrl($url): bool
    {
        assert(is_string($url), 'URL must be a string');

        return (bool) parse_url($url);
    }

    /**
     * 检查传递的参数是否为数组
     * @param mixed $array
     */
    public static function isArray($array): bool
    {
        return is_array($array);
    }

    /**
     * isZeroPrefixed
     *
     * @param string $value
     *
     * @return bool
     */
    public static function isZeroPrefixed($value)
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException('The value to isZeroPrefixed function must be string.');
        }

        return 0 === strpos($value, '0x');
    }

    /**
     * stripZero
     *
     * @param string $value
     *
     * @return string
     */
    public static function stripZero($value)
    {
        if (self::isZeroPrefixed($value)) {
            $count = 1;

            return str_replace('0x', '', $value, $count);
        }

        return $value;
    }

    /**
     * isNegative
     *
     * @param string $value
     *
     * @return bool
     */
    public static function isNegative($value)
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException('The value to isNegative function must be string.');
        }

        return 0 === strpos($value, '-');
    }

    /**
     * 检查字符串是否为十六进制表示法
     * @param mixed $str
     */
    public static function isHex($str): bool
    {
        return is_string($str) and ctype_xdigit($str);
    }

    /**
     * hexToBin
     *
     * @param string $value
     *
     * @return string
     */
    public static function hexToBin($value)
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException('The value to hexToBin function must be string.');
        }
        if (self::isZeroPrefixed($value)) {
            $count = 1;
            $value = str_replace('0x', '', $value, $count);
        }

        return pack('H*', $value);
    }

    /**
     * @param string $address
     * @return bool
     * @throws \Exception
     */
    public static function validate($address)
    {
        assert(is_string($address), 'Address must be a string');
        $decoded = Base58::decode($address);

        $d1 = hash('sha256', substr($decoded, 0, 21), true);
        $d2 = hash('sha256', $d1, true);

        if (0 !== substr_compare($decoded, $d2, 21, 4)) {
            throw new ValidationException('bad digest');
        }

        return true;
    }

    /**
     * @param string $input
     * @return string
     * @throws \Exception
     */
    public static function decodeBase58($input)
    {
        assert(is_string($input), 'Input must be a string');
        $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

        $out = array_fill(0, 25, 0);
        $inputLength = strlen($input);
        for ($i = 0; $i < $inputLength; ++$i) {
            $char = $input[$i];
            if (($p = strpos($alphabet, $char)) === false) {
                throw new ValidationException('invalid character found');
            }
            $c = $p;
            for ($j = 25; --$j;) {
                $c += (int) (58 * $out[$j]);
                $out[$j] = $c % 256;
                $c /= 256;
                $c = (int) $c;
            }
            if (0 !== $c) {
                throw new ValidationException('address too long');
            }
        }

        $result = '';
        foreach ($out as $val) {
            $result .= chr($val);
        }

        return $result;
    }

    /**
     * @param string $pubkey
     * @return string
     * @throws \Exception
     */
    public static function pubKeyToAddress($pubkey)
    {
        assert(is_string($pubkey), 'Public key must be a string');
        $hexBin = hex2bin($pubkey);
        if (false === $hexBin) {
            throw new ValidationException('Invalid hex string');
        }

        return '41' . substr(Keccak::hash(substr($hexBin, 1), 256), 24);
    }

    /**
     * 测试字符串是否以 "0x" 为前缀
     *
     * @param string $str
     *                    要测试前缀的字符串
     *
     * @return bool
     *              如果字符串具有 "0x" 前缀则返回 TRUE，否则返回 FALSE
     */
    public static function hasHexPrefix($str)
    {
        return '0x' === substr($str, 0, 2);
    }

    /**
     * Remove Hex Prefix "0x".
     *
     * @param string $str
     *
     * @return string
     */
    public static function removeHexPrefix($str)
    {
        if (!self::hasHexPrefix($str)) {
            return $str;
        }

        return substr($str, 2);
    }
}
