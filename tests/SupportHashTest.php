<?php

namespace Tourze\TronAPI\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\TronAPI\Support\Hash;

/**
 * @internal
 */
#[CoversClass(Hash::class)]
class SupportHashTest extends TestCase
{
    public function testSHA256(): void
    {
        $data = 'test';
        $hash = Hash::SHA256($data);
        $this->assertIsString($hash);
        $this->assertNotEmpty($hash);
    }

    public function testRIPEMD160(): void
    {
        $data = 'test';
        $hash = Hash::RIPEMD160($data);
        $this->assertIsString($hash);
        $this->assertNotEmpty($hash);
    }
}
