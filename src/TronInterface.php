<?php

declare(strict_types=1);

namespace Tourze\TronAPI;

use Tourze\TronAPI\Exception\TronException;
use Tourze\TronAPI\ValueObject\AccountInfo;
use Tourze\TronAPI\ValueObject\AddressValidation;
use Tourze\TronAPI\ValueObject\BlockInfo;
use Tourze\TronAPI\ValueObject\TransactionInfo;

interface TronInterface
{
    /**
     * 设置管理器节点的链接
     */
    public function setManager(TronManager $providers): void;

    /**
     * 设置您的私钥
     */
    public function setPrivateKey(string $privateKey): void;

    /**
     * 设置您的账户地址
     */
    public function setAddress(string $address): void;

    /**
     * 获取余额
     *
     * @return float|int 余额（TRX单位）
     */
    public function getBalance(string $address, bool $fromTron = false): float|int;

    /**
     * 根据 ID 查询交易
     *
     * @return TransactionInfo|array<string, mixed> 交易信息VO或原始数组
     */
    public function getTransaction(string $transactionID): TransactionInfo|array;

    /**
     * 统计网络上的所有交易
     *
     * @return int
     */
    public function getTransactionCount(): int;

    /**
     * 发送交易到区块链
     *
     * @return TransactionInfo|array<string, mixed> 交易结果VO或原始数组
     *
     * @throws TronException
     */
    public function sendTransaction(string $to, float $amount, ?string $from = null, ?string $message = null): TransactionInfo|array;

    /**
     * 创建账户
     * 使用已激活的账户创建新账户
     *
     * @return TransactionInfo|array<string, mixed> 交易结果VO或原始数组
     */
    public function registerAccount(string $address, string $newAccountAddress): TransactionInfo|array;

    /**
     * 申请成为超级代表
     *
     * @return TransactionInfo|array<string, mixed> 交易结果VO或原始数组
     */
    public function applyForSuperRepresentative(string $address, string $url): TransactionInfo|array;

    /**
     * 使用 HashString 或 blockNumber 获取区块详情
     *
     * @param string|int|null $block
     *
     * @return BlockInfo|array<string, mixed> 区块信息VO或原始数组
     */
    public function getBlock(string|int|null $block = null): BlockInfo|array;

    /**
     * 查询最新的区块
     *
     * @return array<int, BlockInfo>|array<string, mixed> 区块列表或原始数组
     */
    public function getLatestBlocks(int $limit = 1): array;

    /**
     * 验证地址
     *
     * @return AddressValidation|array<string, mixed> 验证结果VO或原始数组
     */
    public function validateAddress(string $address, bool $hex = false): AddressValidation|array;

    /**
     * 生成新地址
     */
    public function generateAddress(): TronAddress;

    /**
     * 在转换为十六进制之前检查地址
     *
     * @param string $sHexAddress
     * @return string
     */
    public function address2HexString(string $sHexAddress): string;
}
