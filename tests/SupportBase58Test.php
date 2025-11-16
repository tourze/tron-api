<?php

namespace Tourze\TronAPI\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\TronAPI\Support\Base58;

/**
 * @internal
 */
#[CoversClass(Base58::class)]
class SupportBase58Test extends TestCase
{
    public function testEncodeAndDecode(): void
    {
        // Base58 encode expects numeric string (big integer)
        $numericData = '12345678901234567890';
        $encoded = Base58::encode($numericData);
        $this->assertIsString($encoded);
        $this->assertNotEmpty($encoded);

        $decoded = Base58::decode($encoded);
        $this->assertSame($numericData, $decoded);
    }
}
