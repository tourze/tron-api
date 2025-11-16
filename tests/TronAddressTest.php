<?php

namespace Tourze\TronAPI\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\TronAPI\TronAddress;

/**
 * @internal
 */
#[CoversClass(TronAddress::class)]
class TronAddressTest extends TestCase
{
    public function testClassExists(): void
    {
        $instance = new TronAddress([
            'private_key' => '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef',
            'public_key' => '04abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890',
            'address_hex' => '41abcdef1234567890abcdef1234567890abcdef12',
            'address_base58' => 'TQj8Y1234567890123456789012345',
        ]);
        $this->assertInstanceOf(TronAddress::class, $instance);
    }

    public function testCanBeInstantiated(): void
    {
        $address = new TronAddress([
            'private_key' => '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef',
            'public_key' => '04abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890',
            'address_hex' => '41abcdef1234567890abcdef1234567890abcdef12',
            'address_base58' => 'TQj8Y1234567890123456789012345',
        ]);
        $this->assertInstanceOf(TronAddress::class, $address);
    }

    public function testGetAddress(): void
    {
        $address = new TronAddress([
            'private_key' => '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef',
            'public_key' => '04abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890',
            'address_hex' => '41abcdef1234567890abcdef1234567890abcdef12',
            'address_base58' => 'TQj8Y1234567890123456789012345',
        ]);
        $this->assertSame('41abcdef1234567890abcdef1234567890abcdef12', $address->getAddress(false));
        $this->assertSame('TQj8Y1234567890123456789012345', $address->getAddress(true));
    }

    public function testGetPublicKey(): void
    {
        $publicKey = '04abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890';
        $address = new TronAddress([
            'private_key' => '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef',
            'public_key' => $publicKey,
            'address_hex' => '41abcdef1234567890abcdef1234567890abcdef12',
            'address_base58' => 'TQj8Y1234567890123456789012345',
        ]);
        $this->assertSame($publicKey, $address->getPublicKey());
    }

    public function testGetPrivateKey(): void
    {
        $privateKey = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';
        $address = new TronAddress([
            'private_key' => $privateKey,
            'public_key' => '04abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890',
            'address_hex' => '41abcdef1234567890abcdef1234567890abcdef12',
            'address_base58' => 'TQj8Y1234567890123456789012345',
        ]);
        $this->assertSame($privateKey, $address->getPrivateKey());
    }

    public function testGetRawData(): void
    {
        $data = [
            'private_key' => '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef',
            'public_key' => '04abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890',
            'address_hex' => '41abcdef1234567890abcdef1234567890abcdef12',
            'address_base58' => 'TQj8Y1234567890123456789012345',
        ];
        $address = new TronAddress($data);
        $this->assertSame($data, $address->getRawData());
    }
}
