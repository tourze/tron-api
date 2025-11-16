<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Support;

use Tourze\TronAPI\Exception\UnsupportedOperationException;

final class Keccak
{
    private const KECCAK_ROUNDS = 24;
    private const LFSR = 0x01;
    private const ENCODING = '8bit';

    /** @var array<int, int> */
    private static $keccakf_rotc = [1, 3, 6, 10, 15, 21, 28, 36, 45, 55, 2, 14, 27, 41, 56, 8, 25, 43, 62, 18, 39, 61, 20, 44];

    /** @var array<int, int> */
    private static $keccakf_piln = [10, 7, 11, 17, 18, 3, 5, 16, 8, 21, 24, 4, 15, 23, 19, 13, 12, 2, 20, 14, 22, 9, 6, 1];

    /** @var bool */
    private static $x64 = (PHP_INT_SIZE === 8);

    /**
     * @param array<int, array<int, int>> $st
     * @param-out array<int, array<int, int>> $st
     * @param array<int, array<int, int>> $bc
     * @param-out array<int, array<int, int>> $bc
     */
    private static function theta64(&$st, &$bc): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $bc[$i] = [
                $st[$i][0] ^ $st[$i + 5][0] ^ $st[$i + 10][0] ^ $st[$i + 15][0] ^ $st[$i + 20][0],
                $st[$i][1] ^ $st[$i + 5][1] ^ $st[$i + 10][1] ^ $st[$i + 15][1] ^ $st[$i + 20][1],
            ];
        }

        for ($i = 0; $i < 5; ++$i) {
            $t = [
                $bc[($i + 4) % 5][0] ^ (($bc[($i + 1) % 5][0] << 1) | ($bc[($i + 1) % 5][1] >> 31)) & 0xFFFFFFFF,
                $bc[($i + 4) % 5][1] ^ (($bc[($i + 1) % 5][1] << 1) | ($bc[($i + 1) % 5][0] >> 31)) & 0xFFFFFFFF,
            ];

            for ($j = 0; $j < 25; $j += 5) {
                $st[$j + $i] = [
                    $st[$j + $i][0] ^ $t[0],
                    $st[$j + $i][1] ^ $t[1],
                ];
            }
        }
    }

    /**
     * @param array<int, array<int, int>> $st
     * @param-out array<int, array<int, int>> $st
     * @param array<int, array<int, int>> $bc
     * @param-out array<int, array<int, int>> $bc
     */
    private static function rhoPi64(&$st, &$bc): void
    {
        $t = $st[1];
        for ($i = 0; $i < 24; ++$i) {
            $j = self::$keccakf_piln[$i];
            $bc[0] = $st[$j];

            $n = self::$keccakf_rotc[$i];
            $hi = $t[0];
            $lo = $t[1];
            if ($n >= 32) {
                $n -= 32;
                $hi = $t[1];
                $lo = $t[0];
            }

            $st[$j] = [
                (($hi << $n) | ($lo >> (32 - $n))) & 0xFFFFFFFF,
                (($lo << $n) | ($hi >> (32 - $n))) & 0xFFFFFFFF,
            ];

            $t = $bc[0];
        }
    }

    /**
     * @param array<int, array<int, int>> $st
     * @param-out array<int, array<int, int>> $st
     * @param array<int, array<int, int>> $bc
     * @param-out array<int, array<int, int>> $bc
     */
    private static function chi64(&$st, &$bc): void
    {
        for ($j = 0; $j < 25; $j += 5) {
            for ($i = 0; $i < 5; ++$i) {
                $bc[$i] = $st[$j + $i];
            }
            for ($i = 0; $i < 5; ++$i) {
                $st[$j + $i] = [
                    $st[$j + $i][0] ^ ~$bc[($i + 1) % 5][0] & $bc[($i + 2) % 5][0],
                    $st[$j + $i][1] ^ ~$bc[($i + 1) % 5][1] & $bc[($i + 2) % 5][1],
                ];
            }
        }
    }

    /**
     * @param array<int, array<int, int>> $st
     * @param-out array<int, array<int, int>> $st
     * @param int $rounds
     */
    private static function keccakf64(&$st, $rounds): void
    {
        $keccakf_rndc = [
            [0x00000000, 0x00000001], [0x00000000, 0x00008082], [0x80000000, 0x0000808A], [0x80000000, 0x80008000],
            [0x00000000, 0x0000808B], [0x00000000, 0x80000001], [0x80000000, 0x80008081], [0x80000000, 0x00008009],
            [0x00000000, 0x0000008A], [0x00000000, 0x00000088], [0x00000000, 0x80008009], [0x00000000, 0x8000000A],
            [0x00000000, 0x8000808B], [0x80000000, 0x0000008B], [0x80000000, 0x00008089], [0x80000000, 0x00008003],
            [0x80000000, 0x00008002], [0x80000000, 0x00000080], [0x00000000, 0x0000800A], [0x80000000, 0x8000000A],
            [0x80000000, 0x80008081], [0x80000000, 0x00008080], [0x00000000, 0x80000001], [0x80000000, 0x80008008],
        ];

        $bc = [];
        for ($round = 0; $round < $rounds; ++$round) {
            self::theta64($st, $bc);
            self::rhoPi64($st, $bc);
            self::chi64($st, $bc);

            // Iota
            $st[0] = [
                $st[0][0] ^ $keccakf_rndc[$round][0],
                $st[0][1] ^ $keccakf_rndc[$round][1],
            ];
        }
    }

    /**
     * Absorb data into state for 64-bit version
     * @param array<int, array<int, int>> $st
     * @param-out array<int, array<int, int>> $st
     * @param string $data
     * @param int $rsizw
     * @param int $offset
     */
    private static function absorb64(&$st, string $data, int $rsizw, int $offset = 0): void
    {
        for ($i = 0; $i < $rsizw; ++$i) {
            $t = unpack('V*', mb_substr($data, $i * 8 + $offset, 8, self::ENCODING));
            if (false === $t) {
                $t = [];
            }

            $val1 = $t[2] ?? 0;
            $val2 = $t[1] ?? 0;
            assert(is_int($val1));
            assert(is_int($val2));
            $st[$i] = [
                $st[$i][0] ^ $val1,
                $st[$i][1] ^ $val2,
            ];
        }
    }

    /**
     * @param string $in_raw
     * @param int $capacity
     * @param int $outputlength
     * @param int $suffix
     * @param bool $raw_output
     * @return string
     */
    private static function keccak64($in_raw, int $capacity, int $outputlength, $suffix, bool $raw_output): string
    {
        $capacity /= 8;
        $inlen = mb_strlen($in_raw, self::ENCODING);
        $rsiz = 200 - 2 * $capacity;
        $rsizw = $rsiz / 8;

        $st = [];
        for ($i = 0; $i < 25; ++$i) {
            $st[] = [0, 0];
        }

        $in_t = 0;
        for (; $inlen >= $rsiz; $inlen -= $rsiz, $in_t += $rsiz) {
            self::absorb64($st, $in_raw, $rsizw, $in_t);
            self::keccakf64($st, self::KECCAK_ROUNDS);
        }

        $temp = mb_substr($in_raw, $in_t, $inlen, self::ENCODING);
        $temp = str_pad($temp, $rsiz, "\x0", STR_PAD_RIGHT);
        $temp = substr_replace($temp, chr($suffix), $inlen, 1);
        $temp = substr_replace($temp, chr(ord($temp[intval($rsiz - 1)]) | 0x80), $rsiz - 1, 1);

        self::absorb64($st, $temp, $rsizw);
        self::keccakf64($st, self::KECCAK_ROUNDS);

        $out = '';
        for ($i = 0; $i < 25; ++$i) {
            $out .= pack('V*', $st[$i][1], $st[$i][0]);
        }
        $r = mb_substr($out, 0, $outputlength / 8, self::ENCODING);

        return $raw_output ? $r : bin2hex($r);
    }

    /**
     * @param array<int, array<int, int>> $st
     * @param-out array<int, array<int, int>> $st
     * @param array<int, array<int, int>> $bc
     * @param-out array<int, array<int, int>> $bc
     */
    private static function theta32(&$st, &$bc): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $bc[$i] = [
                $st[$i][0] ^ $st[$i + 5][0] ^ $st[$i + 10][0] ^ $st[$i + 15][0] ^ $st[$i + 20][0],
                $st[$i][1] ^ $st[$i + 5][1] ^ $st[$i + 10][1] ^ $st[$i + 15][1] ^ $st[$i + 20][1],
                $st[$i][2] ^ $st[$i + 5][2] ^ $st[$i + 10][2] ^ $st[$i + 15][2] ^ $st[$i + 20][2],
                $st[$i][3] ^ $st[$i + 5][3] ^ $st[$i + 10][3] ^ $st[$i + 15][3] ^ $st[$i + 20][3],
            ];
        }

        for ($i = 0; $i < 5; ++$i) {
            $t = [
                $bc[($i + 4) % 5][0] ^ ((($bc[($i + 1) % 5][0] << 1) | ($bc[($i + 1) % 5][1] >> 15)) & 0xFFFF),
                $bc[($i + 4) % 5][1] ^ ((($bc[($i + 1) % 5][1] << 1) | ($bc[($i + 1) % 5][2] >> 15)) & 0xFFFF),
                $bc[($i + 4) % 5][2] ^ ((($bc[($i + 1) % 5][2] << 1) | ($bc[($i + 1) % 5][3] >> 15)) & 0xFFFF),
                $bc[($i + 4) % 5][3] ^ ((($bc[($i + 1) % 5][3] << 1) | ($bc[($i + 1) % 5][0] >> 15)) & 0xFFFF),
            ];

            for ($j = 0; $j < 25; $j += 5) {
                $st[$j + $i] = [
                    $st[$j + $i][0] ^ $t[0],
                    $st[$j + $i][1] ^ $t[1],
                    $st[$j + $i][2] ^ $t[2],
                    $st[$j + $i][3] ^ $t[3],
                ];
            }
        }
    }

    /**
     * @param array<int, array<int, int>> $st
     * @param-out array<int, array<int, int>> $st
     * @param array<int, array<int, int>> $bc
     * @param-out array<int, array<int, int>> $bc
     */
    private static function rhoPi32(&$st, &$bc): void
    {
        $t = $st[1];
        for ($i = 0; $i < 24; ++$i) {
            $j = self::$keccakf_piln[$i];
            $bc[0] = $st[$j];

            $n = self::$keccakf_rotc[$i] >> 4;
            $m = self::$keccakf_rotc[$i] % 16;

            $st[$j] = [
                (($t[(0 + $n) % 4] << $m) | ($t[(1 + $n) % 4] >> (16 - $m))) & 0xFFFF,
                (($t[(1 + $n) % 4] << $m) | ($t[(2 + $n) % 4] >> (16 - $m))) & 0xFFFF,
                (($t[(2 + $n) % 4] << $m) | ($t[(3 + $n) % 4] >> (16 - $m))) & 0xFFFF,
                (($t[(3 + $n) % 4] << $m) | ($t[(0 + $n) % 4] >> (16 - $m))) & 0xFFFF,
            ];

            $t = $bc[0];
        }
    }

    /**
     * @param array<int, array<int, int>> $st
     * @param-out array<int, array<int, int>> $st
     * @param array<int, array<int, int>> $bc
     * @param-out array<int, array<int, int>> $bc
     */
    private static function chi32(&$st, &$bc): void
    {
        for ($j = 0; $j < 25; $j += 5) {
            for ($i = 0; $i < 5; ++$i) {
                $bc[$i] = $st[$j + $i];
            }
            for ($i = 0; $i < 5; ++$i) {
                $st[$j + $i] = [
                    $st[$j + $i][0] ^ ~$bc[($i + 1) % 5][0] & $bc[($i + 2) % 5][0],
                    $st[$j + $i][1] ^ ~$bc[($i + 1) % 5][1] & $bc[($i + 2) % 5][1],
                    $st[$j + $i][2] ^ ~$bc[($i + 1) % 5][2] & $bc[($i + 2) % 5][2],
                    $st[$j + $i][3] ^ ~$bc[($i + 1) % 5][3] & $bc[($i + 2) % 5][3],
                ];
            }
        }
    }

    /**
     * @param array<int, array<int, int>> $st
     * @param-out array<int, array<int, int>> $st
     * @param int $rounds
     */
    private static function keccakf32(&$st, $rounds): void
    {
        $keccakf_rndc = [
            [0x0000, 0x0000, 0x0000, 0x0001], [0x0000, 0x0000, 0x0000, 0x8082], [0x8000, 0x0000, 0x0000, 0x0808A], [0x8000, 0x0000, 0x8000, 0x8000],
            [0x0000, 0x0000, 0x0000, 0x808B], [0x0000, 0x0000, 0x8000, 0x0001], [0x8000, 0x0000, 0x8000, 0x08081], [0x8000, 0x0000, 0x0000, 0x8009],
            [0x0000, 0x0000, 0x0000, 0x008A], [0x0000, 0x0000, 0x0000, 0x0088], [0x0000, 0x0000, 0x8000, 0x08009], [0x0000, 0x0000, 0x8000, 0x000A],
            [0x0000, 0x0000, 0x8000, 0x808B], [0x8000, 0x0000, 0x0000, 0x008B], [0x8000, 0x0000, 0x0000, 0x08089], [0x8000, 0x0000, 0x0000, 0x8003],
            [0x8000, 0x0000, 0x0000, 0x8002], [0x8000, 0x0000, 0x0000, 0x0080], [0x0000, 0x0000, 0x0000, 0x0800A], [0x8000, 0x0000, 0x8000, 0x000A],
            [0x8000, 0x0000, 0x8000, 0x8081], [0x8000, 0x0000, 0x0000, 0x8080], [0x0000, 0x0000, 0x8000, 0x00001], [0x8000, 0x0000, 0x8000, 0x8008],
        ];

        $bc = [];
        for ($round = 0; $round < $rounds; ++$round) {
            self::theta32($st, $bc);
            self::rhoPi32($st, $bc);
            self::chi32($st, $bc);

            // Iota
            $st[0] = [
                $st[0][0] ^ $keccakf_rndc[$round][0],
                $st[0][1] ^ $keccakf_rndc[$round][1],
                $st[0][2] ^ $keccakf_rndc[$round][2],
                $st[0][3] ^ $keccakf_rndc[$round][3],
            ];
        }
    }

    /**
     * Absorb data into state for 32-bit version
     * @param array<int, array<int, int>> $st
     * @param-out array<int, array<int, int>> $st
     * @param string $data
     * @param int $rsizw
     * @param int $offset
     */
    private static function absorb32(&$st, string $data, int $rsizw, int $offset = 0): void
    {
        for ($i = 0; $i < $rsizw; ++$i) {
            $t = unpack('v*', mb_substr($data, $i * 8 + $offset, 8, self::ENCODING));
            if (false === $t) {
                $t = [];
            }

            $val1 = $t[4] ?? 0;
            $val2 = $t[3] ?? 0;
            $val3 = $t[2] ?? 0;
            $val4 = $t[1] ?? 0;
            assert(is_int($val1));
            assert(is_int($val2));
            assert(is_int($val3));
            assert(is_int($val4));
            $st[$i] = [
                $st[$i][0] ^ $val1,
                $st[$i][1] ^ $val2,
                $st[$i][2] ^ $val3,
                $st[$i][3] ^ $val4,
            ];
        }
    }

    /**
     * @param string $in_raw
     * @param int $capacity
     * @param int $outputlength
     * @param int $suffix
     * @param bool $raw_output
     * @return string
     */
    private static function keccak32($in_raw, int $capacity, int $outputlength, $suffix, bool $raw_output): string
    {
        $capacity /= 8;
        $inlen = mb_strlen($in_raw, self::ENCODING);
        $rsiz = 200 - 2 * $capacity;
        $rsizw = $rsiz / 8;

        $st = [];
        for ($i = 0; $i < 25; ++$i) {
            $st[] = [0, 0, 0, 0];
        }

        $in_t = 0;
        for (; $inlen >= $rsiz; $inlen -= $rsiz, $in_t += $rsiz) {
            self::absorb32($st, $in_raw, $rsizw, $in_t);
            self::keccakf32($st, self::KECCAK_ROUNDS);
        }

        $temp = mb_substr($in_raw, $in_t, $inlen, self::ENCODING);
        $temp = str_pad($temp, $rsiz, "\x0", STR_PAD_RIGHT);
        $temp = substr_replace($temp, chr($suffix), $inlen, 1);
        $temp = substr_replace($temp, chr(ord($temp[intval($rsiz - 1)]) | 0x80), $rsiz - 1, 1);

        self::absorb32($st, $temp, $rsizw);
        self::keccakf32($st, self::KECCAK_ROUNDS);

        $out = '';
        for ($i = 0; $i < 25; ++$i) {
            $out .= pack('v*', $st[$i][3], $st[$i][2], $st[$i][1], $st[$i][0]);
        }
        $r = mb_substr($out, 0, $outputlength / 8, self::ENCODING);

        return $raw_output ? $r : bin2hex($r);
    }

    /**
     * @param string $in_raw
     * @param int $capacity
     * @param int $outputlength
     * @param int $suffix
     * @param bool $raw_output
     * @return string
     */
    private static function keccak($in_raw, int $capacity, int $outputlength, $suffix, bool $raw_output): string
    {
        return self::$x64
            ? self::keccak64($in_raw, $capacity, $outputlength, $suffix, $raw_output)
            : self::keccak32($in_raw, $capacity, $outputlength, $suffix, $raw_output);
    }

    /**
     * @param string $in
     * @param int $mdlen
     * @param bool $raw_output
     * @return string
     */
    public static function hash($in, int $mdlen, bool $raw_output = false): string
    {
        if (!in_array($mdlen, [224, 256, 384, 512], true)) {
            throw new UnsupportedOperationException('Unsupported Keccak Hash output size.');
        }

        return self::keccak($in, $mdlen, $mdlen, self::LFSR, $raw_output);
    }

    /**
     * @param string $in
     * @param int $security_level
     * @param int $outlen
     * @param bool $raw_output
     * @return string
     */
    public static function shake($in, int $security_level, int $outlen, bool $raw_output = false): string
    {
        if (!in_array($security_level, [128, 256], true)) {
            throw new UnsupportedOperationException('Unsupported Keccak Shake security level.');
        }

        return self::keccak($in, $security_level, $outlen, 0x1F, $raw_output);
    }
}
