<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Service;

use Tourze\TronAPI\Exception\InvalidArgumentException;
use Tourze\TronAPI\Exception\RuntimeException;
use Tourze\TronAPI\Tron;

/**
 * 基础转账服务类
 * 负责处理TRX和Token的转账操作
 */
class TransferService
{
    protected Tron $tron;

    public function __construct(Tron $tron)
    {
        $this->tron = $tron;
    }

    /**
     * 创建转账交易
     * 如果接收者地址不存在，将在区块链上创建对应的账户
     *
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function sendTrx(string $to, float $amount, ?string $from = null, ?string $message = null): array
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('Invalid amount provided');
        }

        if (is_null($from)) {
            $from = $this->tron->address['hex'];
        }

        // Ensure from is not null after assignment
        assert(null !== $from);

        $to = $this->tron->address2HexString($to);
        $from = $this->tron->address2HexString($from);

        if ($from === $to) {
            throw new InvalidArgumentException('Cannot transfer TRX to the same account');
        }

        $options = [
            'to_address' => $to,
            'owner_address' => $from,
            'amount' => $this->tron->toTron($amount),
        ];

        if (!is_null($message)) {
            $options['extra_data'] = $this->tron->stringUtf8toHex($message);
        }

        return $this->tron->getManager()->request('wallet/createtransaction', $options);
    }

    /**
     * 转账 Token
     *
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function sendToken(string $to, int $amount, string $tokenID, ?string $from = null): array
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Invalid amount provided');
        }

        if (is_null($from)) {
            $from = $this->tron->address['hex'];
        }

        // Ensure from is not null after assignment
        assert(null !== $from);

        if ($to === $from) {
            throw new InvalidArgumentException('Cannot transfer tokens to the same account');
        }

        $transfer = $this->tron->getManager()->request('wallet/transferasset', [
            'owner_address' => $this->tron->address2HexString($from),
            'to_address' => $this->tron->address2HexString($to),
            'asset_name' => $this->tron->stringUtf8toHex($tokenID),
            'amount' => intval($amount),
        ]);

        if (array_key_exists('Error', $transfer)) {
            $errorMessage = is_string($transfer['Error']) ? $transfer['Error'] : 'Unknown error';
            throw new InvalidArgumentException($errorMessage);
        }

        return $transfer;
    }
}
