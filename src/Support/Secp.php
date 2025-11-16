<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Support;

use kornrunner\Secp256k1;
use kornrunner\Signature\Signature;

class Secp
{
    public static function sign(string $message, string $privateKey): string
    {
        $secp = new Secp256k1();

        $sign = $secp->sign($message, $privateKey, ['canonical' => false]);
        assert($sign instanceof Signature);

        return $sign->toHex() . bin2hex(implode('', array_map('chr', [$sign->getRecoveryParam()])));
    }
}
