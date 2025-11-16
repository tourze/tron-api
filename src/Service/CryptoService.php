<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Service;

use kornrunner\Keccak;
use Mdanter\Ecc\EccFactory;
use Tourze\TronAPI\Exception\InvalidArgumentException;
use Tourze\TronAPI\Exception\RuntimeException;
use Tourze\TronAPI\Tron;

/**
 * 加密和签名服务类
 * 负责处理密钥生成、签名、验证等加密操作
 */
class CryptoService
{
    protected Tron $tron;

    public function __construct(Tron $tron)
    {
        $this->tron = $tron;
    }

    /**
     * 签名交易
     *
     * @param array<string, mixed> $transaction
     * @param string|null $message
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     */
    public function signTransaction(array $transaction, ?string $message = null): array
    {
        $this->validatePrivateKey();
        $this->validateTransactionForSigning($transaction);

        if (null !== $message) {
            $transaction = $this->addMessageToTransaction($transaction, $message);
        }

        return $this->applySignatureToTransaction($transaction);
    }

    /**
     * 生成EC密钥对
     *
     * @return array<string, mixed>
     */
    public function getECKeyPair(): array
    {
        $curves = EccFactory::getSecgCurves();
        $generator = $curves->generator256k1();
        $privateKey = $generator->createPrivateKey();
        $publicKey = $privateKey->getPublicKey();
        $point = $publicKey->getPoint();

        // Encode public key as uncompressed (04 + x + y)
        $pubKeyHex = '04'
            . str_pad(gmp_strval($point->getX(), 16), 64, '0', STR_PAD_LEFT)
            . str_pad(gmp_strval($point->getY(), 16), 64, '0', STR_PAD_LEFT);

        return [
            'private_key' => $privateKey->getSecret(),
            'public_key' => $point,
            'hex_private_key' => gmp_strval($privateKey->getSecret(), 16),
            'hex_public_key' => $pubKeyHex,
        ];
    }

    /**
     * SHA3 哈希
     *
     * @param string $string
     * @param bool $prefix
     * @return string
     */
    public function sha3(string $string, bool $prefix = true): string
    {
        $hash = Keccak::hash($string, 256);

        return $prefix ? '0x' . $hash : $hash;
    }

    /**
     * 验证私钥是否设置
     *
     * @throws RuntimeException
     */
    private function validatePrivateKey(): void
    {
        if (null === $this->tron->privateKey || '' === $this->tron->privateKey) {
            throw new RuntimeException('Missing private key');
        }
    }

    /**
     * 验证交易是否可以签名
     *
     * @param array<string, mixed> $transaction
     * @throws RuntimeException
     */
    private function validateTransactionForSigning(array $transaction): void
    {
        if (isset($transaction['Error'])) {
            $error = $transaction['Error'];
            $errorMessage = is_string($error) ? $error : 'Transaction has error';
            throw new RuntimeException($errorMessage);
        }

        if (isset($transaction['signature'])) {
            throw new RuntimeException('Transaction is already signed');
        }

        if (!isset($transaction['txID']) || !is_string($transaction['txID'])) {
            throw new RuntimeException('Invalid transaction structure: missing txID');
        }

        if (!isset($transaction['raw_data'])) {
            throw new RuntimeException('Transaction raw_data is required');
        }
    }

    /**
     * 为交易添加消息
     *
     * @param array<string, mixed> $transaction
     * @param string $message
     * @return array<string, mixed>
     */
    private function addMessageToTransaction(array $transaction, string $message): array
    {
        if (!isset($transaction['raw_data']) || !is_array($transaction['raw_data'])) {
            throw new RuntimeException('Invalid transaction structure: raw_data must be an array');
        }

        $transaction['raw_data']['data'] = $this->tron->stringUtf8toHex($message);

        return $transaction;
    }

    /**
     * 应用签名到交易
     *
     * @param array<string, mixed> $transaction
     * @return array<string, mixed>
     */
    private function applySignatureToTransaction(array $transaction): array
    {
        $privateKeyHex = $this->tron->privateKey;
        if (!isset($transaction['txID']) || !is_string($transaction['txID'])) {
            throw new RuntimeException('Invalid transaction: txID must be a string');
        }
        $txID = $transaction['txID'];

        $signature = $this->createSignature($txID, $privateKeyHex);

        if (!isset($transaction['signature'])) {
            $transaction['signature'] = [];
        } elseif (!is_array($transaction['signature'])) {
            throw new RuntimeException('Invalid transaction: signature must be an array');
        }

        $transaction['signature'][] = $signature;

        return $transaction;
    }

    /**
     * 创建签名
     *
     * @param string $txID
     * @param string $privateKeyHex
     * @return string
     */
    private function createSignature(string $txID, string $privateKeyHex): string
    {
        // 这里应该实现实际的签名逻辑
        // 为了简化，这里返回一个占位符
        $message = hex2bin($txID);
        if (false === $message) {
            throw new RuntimeException('Failed to decode transaction ID from hex');
        }

        $privateKeyBin = hex2bin($privateKeyHex);
        if (false === $privateKeyBin) {
            throw new RuntimeException('Failed to decode private key from hex');
        }

        // 实际实现应该使用 secp256k1 签名
        return hash_hmac('sha256', $message, $privateKeyBin);
    }
}
