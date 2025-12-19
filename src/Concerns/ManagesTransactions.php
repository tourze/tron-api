<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Concerns;

use Tourze\TronAPI\Exception\RuntimeException;
use Tourze\TronAPI\Exception\InvalidArgumentException;
use Tourze\TronAPI\ValueObject\TransactionInfo;
use Tourze\TronAPI\ValueObject\TransactionReceipt;

/**
 * 交易管理 Trait
 *
 * 提供交易查询、发送、签名等功能
 */
trait ManagesTransactions
{
    /**
     * 根据交易 ID 查询交易
     *
     * @return TransactionInfo|array<string, mixed> 返回 TransactionInfo VO 或原始数组（向后兼容）
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function getTransaction(string $transactionID): TransactionInfo|array
    {
        $response = $this->manager->request('wallet/gettransactionbyid', [
            'value' => $transactionID,
        ]);

        if (!is_array($response) || [] === $response) {
            throw new RuntimeException('Transaction not found');
        }

        return $response;
    }

    /**
     * 根据交易 ID 查询交易（返回 VO）
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function getTransactionVO(string $transactionID): TransactionInfo
    {
        $data = $this->getTransaction($transactionID);
        if ($data instanceof TransactionInfo) {
            return $data;
        }

        return TransactionInfo::fromArray($data);
    }

    /**
     * 根据交易 ID 查询交易费用
     *
     * @return array<string, mixed>
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function getTransactionInfo(string $transactionID): array
    {
        return $this->manager->request('walletsolidity/gettransactioninfobyid', [
            'value' => $transactionID,
        ]);
    }

    /**
     * 根据交易 ID 查询交易费用（返回 VO）
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function getTransactionInfoVO(string $transactionID): TransactionReceipt
    {
        $data = $this->getTransactionInfo($transactionID);

        return TransactionReceipt::fromArray($data);
    }

    /**
     * 查询地址接收的交易列表
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function getTransactionsToAddress(string $address, int $limit = 30, int $offset = 0)
    {
        return $this->getTransactionsRelated($address, 'to', $limit, $offset);
    }

    /**
     * 查询地址发送的交易列表
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function getTransactionsFromAddress(string $address, int $limit = 30, int $offset = 0)
    {
        return $this->getTransactionsRelated($address, 'from', $limit, $offset);
    }

    /**
     * 获取"from"和"to"方向的数据
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function getTransactionsRelated(string $address, string $direction = 'to', int $limit = 30, int $offset = 0)
    {
        $this->validateTransactionDirection($direction);
        $this->validatePaginationParams($limit, $offset);

        $response = $this->manager->request(sprintf('walletextension/gettransactions%sthis', $direction), [
            'account' => ['address' => $this->toHex($address)],
            'limit' => $limit,
            'offset' => $offset,
        ]);

        return array_merge($response, ['direction' => $direction]);
    }

    /**
     * 验证交易方向参数
     *
     * @throws RuntimeException
     */
    private function validateTransactionDirection(string $direction): void
    {
        if (!in_array($direction, ['to', 'from'], true)) {
            throw new RuntimeException('Invalid direction provided: Expected "to", "from"');
        }
    }

    /**
     * 验证分页参数
     */
    private function validatePaginationParams(int $limit, int $offset): void
    {
        if ($limit < 0 || ($offset > 0 && $limit < 1)) {
            throw new RuntimeException('Invalid limit provided');
        }

        if ($offset < 0) {
            throw new RuntimeException('Invalid offset provided');
        }
    }

    /**
     * 统计网络中的所有交易
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function getTransactionCount(): int
    {
        $response = $this->manager->request('wallet/totaltransaction');

        return $this->extractNumericValue($response, 'num');
    }

    /**
     * 从区块获取交易详情
     *
     * @param string|int|null $block
     * @param int  $index
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function getTransactionFromBlock(string|int|null $block = null, int $index = 0): array
    {
        if ($index < 0) {
            throw new RuntimeException('Invalid transaction index provided');
        }

        $block_data = $this->getBlock($block);
        $transactions = $this->extractTransactionsFromBlock($block_data);

        return $this->getTransactionAtIndex($transactions, $index);
    }

    /**
     * 从交易列表中获取指定索引的交易
     *
     * @param mixed $transactions
     * @return array<string, mixed>
     * @throws RuntimeException
     */
    private function getTransactionAtIndex($transactions, int $index): array
    {
        if (!is_array($transactions) || [] === $transactions || count($transactions) <= $index) {
            throw new RuntimeException('Transaction not found in block');
        }

        $transaction = $transactions[$index];
        assert(is_array($transaction), 'Transaction must be an array');
        /** @var array<string, mixed> $transaction */
        if (!is_array($transaction)) {
            throw new RuntimeException('Invalid transaction data structure');
        }

        return $transaction;
    }

    /**
     * 向区块链发送交易
     *
     * @return TransactionInfo|array<string, mixed> 返回 TransactionInfo VO 或原始数组（向后兼容）
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function sendTransaction(string $to, float $amount, ?string $from = null, ?string $message = null): TransactionInfo|array
    {
        if (is_null($from)) {
            $from = $this->address['hex'];
        }

        $transaction = $this->transactionBuilder->sendTrx($to, $amount, $from, $message);
        $signedTransaction = $this->signTransaction($transaction);

        $response = $this->sendRawTransaction($signedTransaction);

        return array_merge($response, $signedTransaction);
    }

    /**
     * 向区块链发送交易（返回 VO）
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function sendTransactionVO(string $to, float $amount, ?string $from = null, ?string $message = null): TransactionInfo
    {
        $data = $this->sendTransaction($to, $amount, $from, $message);
        if ($data instanceof TransactionInfo) {
            return $data;
        }

        return TransactionInfo::fromArray($data);
    }

    /**
     * 向区块链发送 Token 交易
     *
     * @return array<string, mixed>
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function sendTokenTransaction(string $to, float $amount, ?int $tokenID = null, ?string $from = null): array
    {
        if (is_null($from)) {
            $from = $this->address['hex'];
        }

        $transaction = $this->transactionBuilder->sendToken($to, $this->toTron($amount), (string) $tokenID, $from);
        $signedTransaction = $this->signTransaction($transaction);

        $response = $this->sendRawTransaction($signedTransaction);

        return array_merge($response, $signedTransaction);
    }

    /**
     * 签名交易。此 API 存在泄露私钥的风险，
     * 请确保在安全的环境中调用此 API
     *
     * @param array<string, mixed> $transaction
     *
     * @return array<string, mixed>
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function signTransaction(array $transaction, ?string $message = null): array
    {
        return $this->cryptoService->signTransaction($transaction, $message);
    }

    /**
     * 广播已签名的交易
     *
     * @param array<string, mixed> $signedTransaction
     *
     * @return array<string, mixed>
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function sendRawTransaction(array $signedTransaction): array
    {
        if (!array_key_exists('signature', $signedTransaction) || !is_array($signedTransaction['signature'])) {
            throw new RuntimeException('Transaction is not signed');
        }

        return $this->manager->request(
            'wallet/broadcasttransaction',
            $signedTransaction
        );
    }

    /**
     * 向 Tron 账户发送资金（选项2）
     *
     * @return TransactionInfo|array<string, mixed>
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function send(string $to, float $amount, ?string $from = null, ?string $message = null): TransactionInfo|array
    {
        return $this->sendTransaction($to, $amount, $from, $message);
    }

    /**
     * 向 Tron 账户发送资金（选项3）
     *
     * @return TransactionInfo|array<string, mixed>
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function sendTrx(string $to, float $amount, ?string $from = null, ?string $message = null): TransactionInfo|array
    {
        return $this->sendTransaction($to, $amount, $from, $message);
    }
}
