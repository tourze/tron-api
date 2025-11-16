<?php

namespace Tourze\TronAPI\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\TronAPI\Support\Base58Check;

/**
 * @internal
 */
#[CoversClass(Base58Check::class)]
class SupportBase58CheckTest extends TestCase
{
    public function testEncodeAndDecode(): void
    {
        // Base58Check expects hex string input
        $hexData = bin2hex('test data');
        $encoded = Base58Check::encode($hexData);
        $this->assertIsString($encoded);
        $this->assertNotEmpty($encoded);

        // Decode and verify the result is valid hex
        $decoded = Base58Check::decode($encoded);
        $this->assertIsString($decoded);
        $this->assertMatchesRegularExpression('/^[0-9a-f]*$/', $decoded);
    }
}
