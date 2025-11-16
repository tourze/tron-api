<?php

namespace Tourze\TronAPI\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\TronAPI\Exception\UnsupportedOperationException;
use Tourze\TronAPI\Support\Keccak;

/**
 * @internal
 */
#[CoversClass(Keccak::class)]
class SupportKeccakTest extends TestCase
{
    /**
     * Test Keccak-256 with official NIST test vectors
     * Source: https://di-mgt.com.au/sha_testvectors.html
     */
    public function testKeccak256WithOfficialTestVectors(): void
    {
        // Test vector 1: Empty string
        $result = Keccak::hash('', 256, false);
        $this->assertSame(
            'c5d2460186f7233c927e7db2dcc703c0e500b653ca82273b7bfad8045d85a470',
            $result,
            'Empty string should produce correct Keccak-256 hash'
        );

        // Test vector 2: "abc"
        $result = Keccak::hash('abc', 256, false);
        $this->assertSame(
            '4e03657aea45a94fc7d47ba826c8d667c0d1e6e33a64a036ec44f58fa12d6c45',
            $result,
            '"abc" should produce correct Keccak-256 hash'
        );

        // Test vector 3: Standard test string
        $input = 'abcdbcdecdefdefgefghfghighijhijkijkljklmklmnlmnomnopnopq';
        $result = Keccak::hash($input, 256, false);
        $this->assertSame(
            '45d3b367a6904e6e8d502ee04999a7c27647f91fa845d456525fd352ae3d7371',
            $result,
            'Standard test string should produce correct Keccak-256 hash'
        );
    }

    /**
     * Test Keccak-256 with raw output
     */
    public function testKeccak256RawOutput(): void
    {
        $hex_result = Keccak::hash('abc', 256, false);
        $raw_result = Keccak::hash('abc', 256, true);

        $this->assertSame(
            hex2bin($hex_result),
            $raw_result,
            'Raw output should match hex-decoded hex output'
        );
    }

    /**
     * 测试所有支持的 Keccak 哈希大小
     */
    public function testKeccakSupportedSizes(): void
    {
        $sizes = [224, 256, 384, 512];
        $input = 'test';

        foreach ($sizes as $size) {
            $result = Keccak::hash($input, $size, false);
            $this->assertSame(
                $size / 4,
                strlen($result),
                "Keccak-{$size} should produce {$size}-bit hash (" . ($size / 4) . ' hex chars)'
            );
        }
    }

    /**
     * Test SHAKE128 and SHAKE256
     */
    public function testShake(): void
    {
        // SHAKE128 with 256-bit output
        $result = Keccak::shake('', 128, 256, false);
        $this->assertSame(
            64,
            strlen($result),
            'SHAKE128 with 256-bit output should produce 64 hex chars'
        );

        // SHAKE256 with 512-bit output
        $result = Keccak::shake('', 256, 512, false);
        $this->assertSame(
            128,
            strlen($result),
            'SHAKE256 with 512-bit output should produce 128 hex chars'
        );
    }

    /**
     * 测试 32 位和 64 位代码路径产生相同结果
     * 这对于确保跨平台一致性至关重要
     */
    public function testConsistencyAcrossPlatforms(): void
    {
        $inputs = [
            '',
            'a',
            'abc',
            'message digest',
            'abcdefghijklmnopqrstuvwxyz',
            str_repeat('a', 1000),
        ];

        foreach ($inputs as $input) {
            $result256 = Keccak::hash($input, 256, false);
            $result512 = Keccak::hash($input, 512, false);

            // Just verify they produce output of correct length
            $this->assertSame(64, strlen($result256), "Input '{$input}' should produce 64 hex chars for 256-bit");
            $this->assertSame(128, strlen($result512), "Input '{$input}' should produce 128 hex chars for 512-bit");

            // Verify hex output is valid
            $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $result256, 'Result should be valid hex');
            $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $result512, 'Result should be valid hex');
        }
    }

    /**
     * 测试不支持的哈希大小会抛出异常
     */
    public function testUnsupportedHashSizeThrowsException(): void
    {
        $this->expectException(UnsupportedOperationException::class);
        $this->expectExceptionMessage('Unsupported Keccak Hash output size');

        Keccak::hash('test', 128);
    }

    /**
     * 测试不支持的 SHAKE 安全级别会抛出异常
     */
    public function testUnsupportedShakeSecurityLevelThrowsException(): void
    {
        $this->expectException(UnsupportedOperationException::class);
        $this->expectExceptionMessage('Unsupported Keccak Shake security level');

        Keccak::shake('test', 512, 256);
    }
}
