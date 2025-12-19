<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Concerns;

use Tourze\TronAPI\Exception\RuntimeException;
use Tourze\TronAPI\Exception\InvalidArgumentException;
use Tourze\TronAPI\Support\Utils;
use Tourze\TronAPI\ValueObject\BlockInfo;

/**
 * 区块管理 Trait
 *
 * 提供区块查询相关功能
 */
trait ManagesBlocks
{
    /**
     * 获取最新的区块号
     *
     * @return array<string, mixed>
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function getCurrentBlock(): array
    {
        return $this->manager->request('wallet/getnowblock');
    }

    /**
     * 获取最新的区块号（返回 VO 对象）
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function getCurrentBlockVO(): BlockInfo
    {
        $data = $this->getCurrentBlock();

        return BlockInfo::fromArray($data);
    }

    /**
     * 使用哈希字符串或区块号获取区块详情
     *
     * @param string|int|null $block
     *
     * @return BlockInfo|array<string, mixed> 返回 BlockInfo VO 或原始数组（向后兼容）
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function getBlock(string|int|null $block = null): BlockInfo|array
    {
        $block = $this->normalizeBlockIdentifier($block);

        return $this->fetchBlockByIdentifier($block);
    }

    /**
     * 规范化区块标识符
     *
     * @param string|int|null $block
     * @return string|int
     * @throws RuntimeException
     */
    private function normalizeBlockIdentifier($block): string|int
    {
        $block = (is_null($block) ? $this->defaultBlock : $block);

        if (false === $block) {
            throw new RuntimeException('No block identifier provided');
        }

        if ('earliest' === $block) {
            return 0;
        }

        // 确保返回类型符合声明，过滤掉 boolean 类型
        if (is_string($block) || is_int($block)) {
            return $block;
        }

        // 如果是其他类型（如 true），转换为字符串
        return (string) $block;
    }

    /**
     * 根据标识符获取区块
     *
     * @param string|int $block
     * @return array<string, mixed>
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    private function fetchBlockByIdentifier($block): array
    {
        if ('latest' === $block) {
            return $this->getCurrentBlock();
        }

        if (is_string($block) && Utils::isHex($block)) {
            return $this->getBlockByHash($block);
        }

        if (is_int($block)) {
            return $this->getBlockByNumber($block);
        }

        throw new RuntimeException('Invalid block identifier provided');
    }

    /**
     * 使用哈希字符串或区块号获取区块详情（返回 VO 对象）
     *
     * @param string|int|null $block
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function getBlockVO(string|int|null $block = null): BlockInfo
    {
        $data = $this->getBlock($block);
        if ($data instanceof BlockInfo) {
            return $data;
        }

        return BlockInfo::fromArray($data);
    }

    /**
     * 根据区块 ID 查询区块
     *
     * @return array<string, mixed>
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function getBlockByHash(string $hashBlock): array
    {
        return $this->manager->request('wallet/getblockbyid', [
            'value' => $hashBlock,
        ]);
    }

    /**
     * 根据区块高度查询区块
     *
     * @return array<string, mixed>
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function getBlockByNumber(int $blockID): array
    {
        if ($blockID < 0) {
            throw new RuntimeException('Invalid block number provided');
        }

        $response = $this->manager->request('wallet/getblockbynum', [
            'num' => intval($blockID),
        ]);

        if ([] === $response || null === $response) {
            throw new RuntimeException('Block not found');
        }

        return $response;
    }

    /**
     * 获取一个区块中交易总数
     *
     * @param string|int|null $block
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function getBlockTransactionCount(string|int|null $block = null): int
    {
        $blockData = $this->getBlock($block);
        $transactions = $this->extractTransactionsFromBlock($blockData);

        return count($transactions);
    }

    /**
     * 从区块数据中提取交易列表
     *
     * @param BlockInfo|array<string, mixed> $block_data
     * @return array<mixed>
     * @throws RuntimeException
     */
    private function extractTransactionsFromBlock($block_data): array
    {
        if ($block_data instanceof BlockInfo) {
            return $block_data->getTransactions();
        }

        if (!isset($block_data['transactions'])) {
            throw new RuntimeException('Invalid block data structure');
        }

        $transactions = $block_data['transactions'];
        assert(is_array($transactions), 'Transactions must be an array');
        return $transactions;
    }

    /**
     * 按区块高度查询一定范围的区块
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function getBlockRange(int $start = 0, int $end = 30): array
    {
        $this->validateBlockRange($start, $end);

        $response = $this->manager->request('wallet/getblockbylimitnext', [
            'startNum' => intval($start),
            'endNum' => intval($end) + 1,
        ]);

        return $this->extractBlockArray($response);
    }

    /**
     * 验证区块范围
     *
     * @throws RuntimeException
     */
    private function validateBlockRange(int $start, int $end): void
    {
        if ($start < 0) {
            throw new RuntimeException('Invalid start of range provided');
        }

        if ($end <= $start) {
            throw new RuntimeException('Invalid end of range provided');
        }
    }

    /**
     * 从响应中提取区块数组
     *
     * @param mixed $response
     * @return array<int|string, mixed>
     * @throws RuntimeException
     */
    private function extractBlockArray($response): array
    {
        if (!is_array($response) || !isset($response['block']) || !is_array($response['block'])) {
            throw new RuntimeException('Invalid response from API');
        }

        $block = $response['block'];
        /** @var array<int|string, mixed> $block */
        return $block;
    }

    /**
     * 查询最新的区块
     *
     * @return array<int, BlockInfo>|array<string, mixed> 返回 BlockInfo VO 数组或原始数组（向后兼容）
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function getLatestBlocks(int $limit = 1): array
    {
        if ($limit <= 0) {
            throw new RuntimeException('Invalid limit provided');
        }

        $response = $this->manager->request('wallet/getblockbylatestnum', [
            'num' => $limit,
        ]);

        return $this->extractBlockArray($response);
    }

    /**
     * 查询最新的区块（返回 VO 数组）
     *
     * @return array<int, BlockInfo>
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function getLatestBlocksVO(int $limit = 1): array
    {
        $data = $this->getLatestBlocks($limit);

        return $this->convertToBlockInfoArray($data);
    }

    /**
     * 将区块数据数组转换为BlockInfo数组
     *
     * @param array<mixed> $data
     * @return array<int, BlockInfo>
     */
    private function convertToBlockInfoArray(array $data): array
    {
        $blocks = [];
        foreach ($data as $blockData) {
            if (is_array($blockData)) {
                /** @var array<string, mixed> $blockData */
                $blocks[] = BlockInfo::fromArray($blockData);
            }
        }

        return $blocks;
    }
}
