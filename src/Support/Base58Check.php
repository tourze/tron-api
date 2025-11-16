<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Support;

class Base58Check
{
    /**
     * Base58Check 编码
     *
     * @return string
     */
    public static function encode(string $string, int $prefix = 128, bool $compressed = true)
    {
        $decodedString = hex2bin($string);
        if (false === $decodedString) {
            throw new \InvalidArgumentException('Invalid hex string provided to Base58Check::encode');
        }
        $string = $decodedString;

        if ($prefix > 0) {
            $string = chr($prefix) . $string;
        }

        if ($compressed) {
            $string .= chr(0x01);
        }

        $string .= substr(Hash::SHA256(Hash::SHA256($string)), 0, 4);

        $base58 = Base58::encode(Crypto::bin2bc($string));
        for ($i = 0; $i < strlen($string); ++$i) {
            if ("\x00" !== $string[$i]) {
                break;
            }

            $base58 = '1' . $base58;
        }

        return $base58;
    }

    /**
     * Base58Check 解码
     */
    public static function decode(string $string, int $removeLeadingBytes = 1, int $removeTrailingBytes = 4, bool $removeCompression = true): string
    {
        $string = bin2hex(Crypto::bc2bin(Base58::decode($string)));

        // If end bytes: Network type
        if ($removeLeadingBytes > 0) {
            $string = substr($string, $removeLeadingBytes * 2);
        }

        // If the final bytes: Checksum
        if ($removeTrailingBytes > 0) {
            $string = substr($string, 0, -($removeTrailingBytes * 2));
        }

        // If end bytes: compressed byte
        if ($removeCompression) {
            $string = substr($string, 0, -2);
        }

        return $string;
    }
}
