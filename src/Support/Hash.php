<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Support;

class Hash
{
    /**
     * Hashing SHA-256
     *
     * @param string $data
     * @param bool $raw
     *
     * @return string
     */
    public static function SHA256($data, $raw = true)
    {
        assert(is_string($data), 'Data must be a string');

        return hash('sha256', $data, $raw);
    }

    /**
     * Double hashing SHA-256
     *
     * @param string $data
     * @return string
     */
    public static function sha256d($data)
    {
        assert(is_string($data), 'Data must be a string');

        return hash('sha256', hash('sha256', $data, true), true);
    }

    /**
     * Hashing RIPEMD160
     *
     * @param string $data
     * @param bool $raw
     *
     * @return string
     */
    public static function RIPEMD160($data, $raw = true)
    {
        assert(is_string($data), 'Data must be a string');

        return hash('ripemd160', $data, $raw);
    }
}
