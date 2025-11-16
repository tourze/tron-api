<?php

namespace Tourze\TronAPI\Tests\Service;

use Mdanter\Ecc\Primitives\PointInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\TronAPI\Exception\InvalidArgumentException;
use Tourze\TronAPI\Exception\RuntimeException;
use Tourze\TronAPI\Service\CryptoService;
use Tourze\TronAPI\Tron;

/**
 * @internal
 */
#[CoversClass(CryptoService::class)]
class CryptoServiceTest extends TestCase
{
    private CryptoService $cryptoService;

    private Tron $tron;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tron = new Tron();
        $this->cryptoService = new CryptoService($this->tron);
    }

    public function testCanBeInstantiated(): void
    {
        $this->assertInstanceOf(CryptoService::class, $this->cryptoService);
    }

    public function testSignTransactionThrowsExceptionWhenPrivateKeyMissing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing private key');

        $transaction = [
            'txID' => 'abc123def456',
            'raw_data' => [
                'contract' => [],
            ],
        ];

        $this->cryptoService->signTransaction($transaction);
    }

    public function testSignTransactionThrowsExceptionWhenTxIDMissing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid transaction structure: missing txID');

        $this->tron->setPrivateKey('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
        $cryptoService = new CryptoService($this->tron);

        $transaction = [
            'raw_data' => [
                'contract' => [],
            ],
        ];

        $cryptoService->signTransaction($transaction);
    }

    public function testSignTransactionThrowsExceptionWhenRawDataMissing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Transaction raw_data is required');

        $this->tron->setPrivateKey('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
        $cryptoService = new CryptoService($this->tron);

        $transaction = [
            'txID' => 'abc123def456',
        ];

        $cryptoService->signTransaction($transaction);
    }

    public function testSignTransactionSuccess(): void
    {
        $this->tron->setPrivateKey('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
        $cryptoService = new CryptoService($this->tron);

        $transaction = [
            'txID' => 'abc123def456',
            'raw_data' => [
                'contract' => [],
            ],
        ];

        $signed = $cryptoService->signTransaction($transaction);

        $this->assertIsArray($signed);
        $this->assertArrayHasKey('signature', $signed);
        $this->assertIsArray($signed['signature']);
        $this->assertNotEmpty($signed['signature']);
        $this->assertArrayHasKey('txID', $signed);
        $this->assertSame('abc123def456', $signed['txID']);
    }

    public function testSignTransactionWithMessage(): void
    {
        $this->tron->setPrivateKey('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
        $cryptoService = new CryptoService($this->tron);

        $transaction = [
            'txID' => 'abc123def456',
            'raw_data' => [
                'contract' => [],
            ],
        ];

        $message = 'Test message';
        $signed = $cryptoService->signTransaction($transaction, $message);

        $this->assertIsArray($signed);
        $this->assertArrayHasKey('signature', $signed);
        $this->assertArrayHasKey('raw_data', $signed);
        $rawData = $signed['raw_data'];
        $this->assertIsArray($rawData);
        $this->assertArrayHasKey('data', $rawData);
        // Verify the message was converted to hex
        $this->assertIsString($rawData['data']);
        $this->assertNotEmpty($rawData['data']);
    }

    public function testSignTransactionThrowsExceptionWhenAlreadySigned(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Transaction is already signed');

        $this->tron->setPrivateKey('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
        $cryptoService = new CryptoService($this->tron);

        $transaction = [
            'txID' => 'abc123def456',
            'raw_data' => [
                'contract' => [],
            ],
            'signature' => ['existing_signature'],
        ];

        $cryptoService->signTransaction($transaction);
    }

    public function testGetECKeyPair(): void
    {
        $keyPair = $this->cryptoService->getECKeyPair();

        $this->assertIsArray($keyPair);
        $this->assertArrayHasKey('private_key', $keyPair);
        $this->assertArrayHasKey('public_key', $keyPair);
        $this->assertArrayHasKey('hex_private_key', $keyPair);
        $this->assertArrayHasKey('hex_public_key', $keyPair);

        // Verify private_key is a GMP number
        $this->assertInstanceOf(\GMP::class, $keyPair['private_key']);

        // Verify public_key is a Point
        $this->assertInstanceOf(PointInterface::class, $keyPair['public_key']);

        // Verify hex keys are strings
        $this->assertIsString($keyPair['hex_private_key']);
        $this->assertIsString($keyPair['hex_public_key']);

        // Verify hex keys are valid hex
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/i', $keyPair['hex_private_key']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/i', $keyPair['hex_public_key']);
    }

    public function testGetECKeyPairGeneratesDifferentKeys(): void
    {
        $keyPair1 = $this->cryptoService->getECKeyPair();
        $keyPair2 = $this->cryptoService->getECKeyPair();

        // Different invocations should generate different keys
        $this->assertNotSame(
            $keyPair1['hex_private_key'],
            $keyPair2['hex_private_key'],
            'Generated keys should be different on each call'
        );

        $this->assertNotSame(
            $keyPair1['hex_public_key'],
            $keyPair2['hex_public_key'],
            'Generated public keys should be different on each call'
        );
    }

    public function testSha3WithoutPrefix(): void
    {
        $input = 'test';
        $result = $this->cryptoService->sha3($input, false);

        $this->assertIsString($result);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/i', $result);
        $this->assertStringStartsNotWith('0x', $result);
    }

    public function testSha3WithPrefix(): void
    {
        $input = 'test';
        $result = $this->cryptoService->sha3($input, true);

        $this->assertIsString($result);
        $this->assertStringStartsWith('0x', $result);
        $this->assertMatchesRegularExpression('/^0x[0-9a-f]{64}$/i', $result);
    }

    public function testSha3DefaultUsesPrefix(): void
    {
        $input = 'test';
        $result = $this->cryptoService->sha3($input);

        $this->assertStringStartsWith('0x', $result);
    }

    public function testSha3ProducesConsistentResults(): void
    {
        $input = 'test';
        $result1 = $this->cryptoService->sha3($input);
        $result2 = $this->cryptoService->sha3($input);

        $this->assertSame($result1, $result2, 'SHA3 should produce consistent results for same input');
    }

    public function testSha3ProducesDifferentResultsForDifferentInputs(): void
    {
        $result1 = $this->cryptoService->sha3('test1');
        $result2 = $this->cryptoService->sha3('test2');

        $this->assertNotSame($result1, $result2, 'SHA3 should produce different results for different inputs');
    }

    public function testSha3WithEmptyString(): void
    {
        $result = $this->cryptoService->sha3('');

        $this->assertIsString($result);
        $this->assertStringStartsWith('0x', $result);
        $this->assertMatchesRegularExpression('/^0x[0-9a-f]{64}$/i', $result);
    }

    public function testSignTransactionWithNullMessage(): void
    {
        $this->tron->setPrivateKey('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
        $cryptoService = new CryptoService($this->tron);

        $transaction = [
            'txID' => 'abc123def456',
            'raw_data' => [
                'contract' => [],
            ],
        ];

        $signed = $cryptoService->signTransaction($transaction, null);

        $this->assertIsArray($signed);
        $this->assertArrayHasKey('signature', $signed);
        // Should not have data field when message is null
        $rawData = $signed['raw_data'];
        $this->assertIsArray($rawData);
        $this->assertArrayNotHasKey('data', $rawData);
    }

    public function testSignTransactionWithEmptyPrivateKey(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing private key');

        // Set empty string as private key
        $this->tron->setPrivateKey('');
        $cryptoService = new CryptoService($this->tron);

        $transaction = [
            'txID' => 'abc123def456',
            'raw_data' => [
                'contract' => [],
            ],
        ];

        $cryptoService->signTransaction($transaction);
    }

    public function testSignTransactionThrowsExceptionForTransactionError(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Some transaction error');

        $this->tron->setPrivateKey('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
        $cryptoService = new CryptoService($this->tron);

        $transaction = [
            'txID' => 'abc123def456',
            'raw_data' => [
                'contract' => [],
            ],
            'Error' => 'Some transaction error',
        ];

        $cryptoService->signTransaction($transaction);
    }

    public function testSignTransactionThrowsExceptionForInvalidTxIDType(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid transaction structure: missing txID');

        $this->tron->setPrivateKey('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
        $cryptoService = new CryptoService($this->tron);

        $transaction = [
            'txID' => 123, // Not a string
            'raw_data' => [
                'contract' => [],
            ],
        ];

        $cryptoService->signTransaction($transaction);
    }
}
