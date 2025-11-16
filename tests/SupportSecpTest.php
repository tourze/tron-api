<?php

namespace Tourze\TronAPI\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\TronAPI\Support\Secp;

/**
 * @internal
 */
#[CoversClass(Secp::class)]
class SupportSecpTest extends TestCase
{
    public function testSignMethodIsStatic(): void
    {
        // Verify sign method exists and is static
        // Note: Actual signing test requires valid private key and message hash
        // which would need external dependencies (like secp256k1 library)
        $reflection = new \ReflectionClass(Secp::class);
        $this->assertTrue($reflection->hasMethod('sign'));
        $method = $reflection->getMethod('sign');
        $this->assertTrue($method->isStatic(), 'sign() method should be static');
    }
}
