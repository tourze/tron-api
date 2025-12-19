<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Service;

use Tourze\TronAPI\Exception\RuntimeException;
use Tourze\TronAPI\Tron;
use Tourze\TronAPI\ValueObject\AccountInfo;
use Tourze\TronAPI\ValueObject\EventData;
use Tourze\TronAPI\ValueObject\TokenBalance;

/**
 * 区块链查询服务类
 * 负责处理复杂的区块链数据查询操作
 */
class BlockchainQueryService
{
    protected Tron $tron;

    public function __construct(Tron $tron)
    {
        $this->tron = $tron;
    }

    /**
     * 获取指定地址的Token余额（返回原始类型，向后兼容）
     *
     * @return array<string, mixed>|int|float
     * @throws RuntimeException
     */
    public function getTokenBalance(int $tokenId, string $address, bool $fromTron = false)
    {
        $balanceVO = $this->getTokenBalanceVO($tokenId, $address);

        if ($balanceVO->isZero()) {
            return 0;
        }

        $value = $this->ensureNumeric($balanceVO->getRaw());

        return $fromTron ? $this->tron->fromTron($value) : $value;
    }

    /**
     * 获取指定地址的Token余额（返回VO对象）
     *
     * @throws RuntimeException
     */
    public function getTokenBalanceVO(int $tokenId, string $address): TokenBalance
    {
        // 直接调用 getAccount() 以保持与现有测试的兼容性
        $account = $this->tron->getAccount($address);
        $accountInfo = AccountInfo::fromArray($account);

        // 如果账户没有任何资产，返回零余额
        if ([] === $accountInfo->getAssetV2()) {
            return TokenBalance::fromRaw('0', 0);
        }

        $assetBalance = $accountInfo->findAssetBalance($tokenId);

        if (null === $assetBalance) {
            throw new RuntimeException('Token id not found');
        }

        $rawBalance = (string) $assetBalance;

        // Note: 这里使用0作为decimals，因为TRC10 token的余额已经是最小单位
        return TokenBalance::fromRaw($rawBalance, 0);
    }

    /**
     * 获取复杂的事件结果（返回数组，向后兼容）
     *
     * @param mixed $contractAddress
     * @param int $sinceTimestamp
     * @param string|null $eventName
     * @param int $blockNumber
     * @return array<int, array<string, mixed>>
     */
    public function getEventResult($contractAddress, int $sinceTimestamp = 0, ?string $eventName = null, int $blockNumber = 0): array
    {
        $events = $this->getEvents($contractAddress, $sinceTimestamp, $eventName, $blockNumber);

        return array_map(fn (EventData $event) => $event->toArray(), $events);
    }

    /**
     * 获取复杂的事件结果（返回VO对象数组）
     *
     * @param mixed $contractAddress
     * @return array<int, EventData>
     */
    public function getEvents($contractAddress, int $sinceTimestamp = 0, ?string $eventName = null, int $blockNumber = 0): array
    {
        /** @var array<string, mixed> $routeParams */
        $routeParams = [];

        if (!is_null($contractAddress)) {
            $routeParams['contract_address'] = $contractAddress;
        }

        if ($sinceTimestamp > 0) {
            $routeParams['since_timestamp'] = $sinceTimestamp;
        }

        if (!is_null($eventName)) {
            $routeParams['event_name'] = $eventName;
        }

        if ($blockNumber > 0) {
            $routeParams['block_number'] = $blockNumber;
        }

        if (0 === count($routeParams)) {
            return [];
        }

        $queryString = http_build_query($routeParams);
        $response = $this->tron->getManager()->request('v1/contracts/events?' . $queryString, [], 'get');

        if (!isset($response['data']) || !is_array($response['data'])) {
            return [];
        }

        // 确保返回的是索引数组
        /** @var array<int, array<string, mixed>> $rawEvents */
        $rawEvents = array_values($response['data']);

        return EventData::fromArrayBatch($rawEvents);
    }

    /**
     * 获取地址相关的交易
     *
     * @return array<string, mixed>
     */
    public function getTransactionsRelated(string $address, string $direction = 'to', int $limit = 30, int $offset = 0): array
    {
        /** @var array<string, mixed> $transactions */
        $transactions = [];

        if ('to' === $direction) {
            $transactions = $this->getTransactionsToAddress($address, $limit, $offset);
        } elseif ('from' === $direction) {
            $transactions = $this->getTransactionsFromAddress($address, $limit, $offset);
        } elseif ('all' === $direction) {
            $transactionsTo = $this->getTransactionsToAddress($address, $limit, $offset);
            $transactionsFrom = $this->getTransactionsFromAddress($address, $limit, $offset);

            if (isset($transactionsTo['data']) && is_array($transactionsTo['data'])
                && isset($transactionsFrom['data']) && is_array($transactionsFrom['data'])) {
                $transactions['data'] = array_merge($transactionsTo['data'], $transactionsFrom['data']);
            }
        }

        return $transactions;
    }

    /**
     * 获取指定地址的转入交易
     *
     * @return array<string, mixed>
     */
    private function getTransactionsToAddress(string $address, int $limit, int $offset): array
    {
        return $this->tron->getManager()->request('v1/accounts/' . $address . '/transactions/trc20?limit=' . $limit . '&start=' . $offset, [], 'get');
    }

    /**
     * 获取指定地址的转出交易
     *
     * @return array<string, mixed>
     */
    private function getTransactionsFromAddress(string $address, int $limit, int $offset): array
    {
        return $this->tron->getManager()->request('v1/accounts/' . $address . '/transactions?only_from=true&limit=' . $limit . '&start=' . $offset, [], 'get');
    }

    /**
     * 确保值为数字类型（int或float）
     *
     * @param mixed $value
     * @return int|float
     * @throws RuntimeException
     */
    private function ensureNumeric(mixed $value): int|float
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            // 如果包含小数点，返回 float，否则返回 int
            if (str_contains($value, '.')) {
                return (float) $value;
            }

            return (int) $value;
        }

        throw new RuntimeException('Value must be numeric');
    }
}
