<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Tests\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\TronAPI\ValueObject\NodeInfo;

/**
 * @internal
 */
#[CoversClass(NodeInfo::class)]
class NodeInfoTest extends TestCase
{
    public function testCanBeCreatedFromAddress(): void
    {
        $address = '192.168.1.1:8090';
        $nodeInfo = NodeInfo::fromAddress($address);

        $this->assertInstanceOf(NodeInfo::class, $nodeInfo);
        $this->assertSame('192.168.1.1', $nodeInfo->getHost());
        $this->assertSame(8090, $nodeInfo->getPort());
        $this->assertSame('192.168.1.1:8090', $nodeInfo->getAddress());
        $this->assertTrue($nodeInfo->isValid());
    }

    public function testCanBeCreatedFromArray(): void
    {
        $data = [
            'address' => [
                'host' => '10.0.0.1',
                'port' => 18090,
            ],
        ];

        $nodeInfo = NodeInfo::fromArray($data);

        $this->assertSame('10.0.0.1', $nodeInfo->getHost());
        $this->assertSame(18090, $nodeInfo->getPort());
        $this->assertSame('10.0.0.1:18090', $nodeInfo->getAddress());
        $this->assertTrue($nodeInfo->isValid());
    }

    public function testFromAddressHandlesInvalidFormat(): void
    {
        $nodeInfo = NodeInfo::fromAddress('invalid');

        $this->assertSame('invalid', $nodeInfo->getHost());
        $this->assertSame(0, $nodeInfo->getPort());
        $this->assertSame('invalid', $nodeInfo->getAddress());
        $this->assertFalse($nodeInfo->isValid());
    }

    public function testFromArrayHandlesEmptyData(): void
    {
        $data = [];
        $nodeInfo = NodeInfo::fromArray($data);

        $this->assertSame('', $nodeInfo->getHost());
        $this->assertSame(0, $nodeInfo->getPort());
        $this->assertSame('', $nodeInfo->getAddress());
        $this->assertFalse($nodeInfo->isValid());
    }

    public function testIsValidReturnsFalseForEmptyHost(): void
    {
        $data = [
            'address' => [
                'host' => '',
                'port' => 8090,
            ],
        ];

        $nodeInfo = NodeInfo::fromArray($data);

        $this->assertFalse($nodeInfo->isValid());
    }

    public function testIsValidReturnsFalseForZeroPort(): void
    {
        $data = [
            'address' => [
                'host' => '192.168.1.1',
                'port' => 0,
            ],
        ];

        $nodeInfo = NodeInfo::fromArray($data);

        $this->assertFalse($nodeInfo->isValid());
    }

    public function testToStringReturnsAddress(): void
    {
        $address = '192.168.1.1:8090';
        $nodeInfo = NodeInfo::fromAddress($address);

        $this->assertSame('192.168.1.1:8090', (string) $nodeInfo);
    }

    public function testToArrayReturnsCorrectStructure(): void
    {
        $nodeInfo = NodeInfo::fromAddress('192.168.1.1:8090');
        $array = $nodeInfo->toArray();

        $this->assertSame([
            'host' => '192.168.1.1',
            'port' => 8090,
            'address' => '192.168.1.1:8090',
        ], $array);
    }
}
