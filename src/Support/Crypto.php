<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Support;

use Tourze\TronAPI\Exception\InvalidArgumentException;
use Tourze\TronAPI\Exception\RuntimeException;

class Crypto
{
    /**
     * @param string $num
     * @return string
     */
    public static function bc2bin($num)
    {
        return self::dec2base($num, 256);
    }

    /**
     * @param string $dec
     * @param int<2, 256> $base
     * @param string|false $digits
     * @return string
     */
    public static function dec2base($dec, $base, $digits = false)
    {
        if (extension_loaded('bcmath')) {
            if ($base < 2 || $base > 256) {
                throw new InvalidArgumentException('Invalid Base: ' . $base);
            }
            bcscale(0);
            $value = '';
            if (false === $digits) {
                $digits = self::digits($base);
            }
            while ($dec > $base - 1) {
                $rest = bcmod($dec, (string) $base);
                $dec = bcdiv($dec, (string) $base);
                $value = $digits[(int) $rest] . $value;
            }
            $value = $digits[intval($dec)] . $value;

            return $value;
        }
        throw new RuntimeException('Please install BCMATH');
    }

    /**
     * @param string $value
     * @param int $base
     * @param string|false $digits
     * @return string
     */
    public static function base2dec(string $value, int $base, string|false $digits = false): string
    {
        if (extension_loaded('bcmath')) {
            if ($base < 2 || $base > 256) {
                throw new InvalidArgumentException('Invalid Base: ' . $base);
            }
            bcscale(0);
            if ($base < 37) {
                $value = strtolower($value);
            }
            if (false === $digits) {
                $digits = self::digits($base);
            }
            $size = strlen($value);
            $dec = '0';
            for ($loop = 0; $loop < $size; ++$loop) {
                $element = strpos($digits, $value[$loop]);
                if (false === $element) {
                    $element = 0;
                }
                $power = bcpow((string) $base, (string) ($size - $loop - 1));
                $dec = bcadd($dec, bcmul((string) $element, $power));
            }

            return $dec;
        }
        throw new RuntimeException('Please install BCMATH');
    }

    /**
     * @param int $base
     * @return string
     */
    public static function digits($base)
    {
        if ($base > 64) {
            $digits = '';
            for ($loop = 0; $loop < 256; ++$loop) {
                $digits .= chr($loop);
            }
        } else {
            $digits = '0123456789abcdefghijklmnopqrstuvwxyz';
            $digits .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ-_';
        }
        $digits = substr($digits, 0, $base);

        return $digits;
    }

    /**
     * @param string $num
     * @return string
     */
    public static function bin2bc($num)
    {
        return self::base2dec($num, 256);
    }
}
