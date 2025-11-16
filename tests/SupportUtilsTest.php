<?php

namespace Tourze\TronAPI\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\TronAPI\Support\Utils;

/**
 * @internal
 */
#[CoversClass(Utils::class)]
class SupportUtilsTest extends TestCase
{
    public function testIsHex(): void
    {
        $this->assertTrue(Utils::isHex('abcdef123'));
        $this->assertFalse(Utils::isHex('xyz'));
    }

    public function testIsArray(): void
    {
        $this->assertTrue(Utils::isArray([]));
        $this->assertFalse(Utils::isArray('string'));
    }

    public function testHasHexPrefix(): void
    {
        $this->assertTrue(Utils::hasHexPrefix('0x123'));
        $this->assertFalse(Utils::hasHexPrefix('123'));
    }

    public function testRemoveHexPrefix(): void
    {
        $this->assertSame('123', Utils::removeHexPrefix('0x123'));
        $this->assertSame('123', Utils::removeHexPrefix('123'));
    }
}
