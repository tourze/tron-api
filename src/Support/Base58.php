<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Support;

class Base58
{
    /**
     * 将传入的完整字符串编码为 base58
     *
     * @param string $num
     * @param int<2, 256> $length
     */
    public static function encode($num, $length = 58): string
    {
        assert(is_string($num), 'Number must be a string');

        return Crypto::dec2base($num, $length, '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz');
    }

    /**
     * 将 Base58 解码为大整数字符串
     */
    public static function decode(string $addr, int $length = 58): string
    {
        return Crypto::base2dec($addr, $length, '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz');
    }
}
