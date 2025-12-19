<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Concerns;

use Tourze\TronAPI\Exception\RuntimeException;
use Tourze\TronAPI\Exception\InvalidArgumentException;

/**
 * Token 管理 Trait
 *
 * 提供 Token 创建、查询、转账等功能
 */
trait ManagesTokens
{
    /**
     * 基于 Tron 创建新的 Token
     *
     * Token 参数示例：
     * - owner_address: "41e552f6487585c2b58bc2c9bb4492bc1f17132cd0"
     * - name: "0x6173736574497373756531353330383934333132313538"
     * - abbr: "0x6162627231353330383934333132313538"
     * - total_supply: 4321
     * - trx_num: 1
     * - num: 1
     * - start_time: 1530894315158
     * - end_time: 1533894312158
     * - description: "007570646174654e616d6531353330363038383733343633"
     * - url: "007570646174654e616d6531353330363038383733343633"
     * - free_asset_net_limit: 10000
     * - public_free_asset_net_limit: 10000
     * - frozen_supply: ["frozen_amount" => 1, "frozen_days" => 2]
     *
     * @param array<string, mixed>|mixed $token Token配置数组
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function createToken($token = [])
    {
        if (!is_array($token)) {
            $token = [];
        }

        /** @var array<string, mixed> $token */
        $safe_token = $this->normalizeCreateTokenParams($token);
        $request_data = $this->buildCreateTokenRequest($safe_token);

        return $this->manager->request('wallet/createassetissue', $request_data);
    }

    /**
     * @param array<string, mixed> $token
     * @return array<string, mixed>
     */
    private function normalizeCreateTokenParams(array $token): array
    {
        $currentTime = time() * 1000;

        return [
            'owner_address' => $token['owner_address'] ?? '',
            'name' => $token['name'] ?? '',
            'abbr' => $token['abbr'] ?? '',
            'description' => $token['description'] ?? '',
            'url' => $token['url'] ?? '',
            'total_supply' => $token['total_supply'] ?? 0,
            'trx_num' => $token['trx_num'] ?? 1,
            'num' => $token['num'] ?? 1,
            'start_time' => $token['start_time'] ?? $currentTime,
            'end_time' => $token['end_time'] ?? ($currentTime + 86400000),
            'free_asset_net_limit' => $token['free_asset_net_limit'] ?? 0,
            'public_free_asset_net_limit' => $token['public_free_asset_net_limit'] ?? 0,
            'frozen_supply' => $token['frozen_supply'] ?? [],
        ];
    }

    /**
     * @param array<string, mixed> $safe_token
     * @return array<string, mixed>
     */
    private function buildCreateTokenRequest(array $safe_token): array
    {
        return [
            'owner_address' => $this->convertToHex($safe_token, 'owner_address'),
            'name' => $this->convertToUtf8Hex($safe_token, 'name'),
            'abbr' => $this->convertToUtf8Hex($safe_token, 'abbr'),
            'description' => $this->convertToUtf8Hex($safe_token, 'description'),
            'url' => $this->convertToUtf8Hex($safe_token, 'url'),
            'total_supply' => $safe_token['total_supply'],
            'trx_num' => $safe_token['trx_num'],
            'num' => $safe_token['num'],
            'start_time' => $safe_token['start_time'],
            'end_time' => $safe_token['end_time'],
            'free_asset_net_limit' => $safe_token['free_asset_net_limit'],
            'public_free_asset_net_limit' => $safe_token['public_free_asset_net_limit'],
            'frozen_supply' => $safe_token['frozen_supply'],
        ];
    }

    /**
     * 安全转换为Hex
     *
     * @param array<string, mixed> $data
     */
    private function convertToHex(array $data, string $key): string
    {
        $value = $data[$key] ?? '';

        return $this->toHex(is_string($value) ? $value : '');
    }

    /**
     * 安全转换为UTF8 Hex
     *
     * @param array<string, mixed> $data
     */
    private function convertToUtf8Hex(array $data, string $key): string
    {
        $value = $data[$key] ?? '';

        return $this->stringUtf8toHex(is_string($value) ? $value : '');
    }

    /**
     * 转账 Token
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function sendToken(string $to, int $amount, string $tokenID, ?string $from = null)
    {
        if (null === $from) {
            $from = $this->address['hex'];
        }

        $transfer = $this->transactionBuilder->sendToken($to, $amount, $tokenID, $from);
        $signedTransaction = $this->signTransaction($transfer);
        $response = $this->sendRawTransaction($signedTransaction);

        return array_merge($response, $signedTransaction);
    }

    /**
     * 购买 Token
     *
     * @param string $issuerAddress
     * @param string $tokenID
     * @param int $amount
     * @param string|null $buyer
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function purchaseToken(string $issuerAddress, string $tokenID, int $amount, ?string $buyer = null)
    {
        $buyer = $this->normalizeBuyerAddress($buyer);

        $purchase = $this->transactionBuilder->purchaseToken($issuerAddress, $tokenID, $amount, $buyer);
        $signedTransaction = $this->signTransaction($purchase);
        $response = $this->sendRawTransaction($signedTransaction);

        return array_merge($response, $signedTransaction);
    }

    /**
     * 规范化买家地址
     *
     * @param mixed $buyer
     */
    private function normalizeBuyerAddress($buyer): string
    {
        if (null === $buyer) {
            $buyer = $this->address['hex'];
        }

        assert(is_string($buyer), 'buyer must be a string');

        return $buyer;
    }

    /**
     * 更新 Token 信息
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function updateToken(
        string $description,
        string $url,
        int $freeBandwidth = 0,
        int $freeBandwidthLimit = 0,
        ?string $owner_address = null
    ) {
        if (null === $owner_address) {
            $owner_address = $this->address['hex'];
        }

        $withdraw = $this->transactionBuilder->updateToken($description, $url, $freeBandwidth, $freeBandwidthLimit, $owner_address);
        $signedTransaction = $this->signTransaction($withdraw);
        $response = $this->sendRawTransaction($signedTransaction);

        return array_merge($response, $signedTransaction);
    }

    /**
     * 查询 Token 列表（支持分页）
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function listTokens(int $limit = 0, int $offset = 0): array
    {
        // 验证分页参数
        if ($limit < 0 || ($offset > 0 && $limit < 1)) {
            throw new RuntimeException('Invalid limit provided');
        }

        if ($offset < 0) {
            throw new RuntimeException('Invalid offset provided');
        }

        if (0 === $limit) {
            return $this->fetchAllTokens();
        }

        return $this->fetchPaginatedTokens($limit, $offset);
    }

    /**
     * 获取所有Token
     *
     * @return array<string, mixed>
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    private function fetchAllTokens(): array
    {
        $response = $this->manager->request('wallet/getassetissuelist');

        return $this->extractAssetIssue($response);
    }

    /**
     * 获取分页Token
     *
     * @return array<string, mixed>
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    private function fetchPaginatedTokens(int $limit, int $offset): array
    {
        $response = $this->manager->request('wallet/getpaginatedassetissuelist', [
            'offset' => intval($offset),
            'limit' => intval($limit),
        ]);

        return $this->extractAssetIssue($response);
    }

    /**
     * 从响应中提取assetIssue
     *
     * @param mixed $response
     * @return array<string, mixed>
     * @throws RuntimeException
     */
    private function extractAssetIssue($response): array
    {
        if (!is_array($response) || !isset($response['assetIssue']) || !is_array($response['assetIssue'])) {
            throw new RuntimeException('Invalid response from API');
        }

        /** @var array<string, mixed> $assetIssue */
        $assetIssue = $response['assetIssue'];
        return $assetIssue;
    }

    /**
     * 按 ID 查询 Token
     *
     * @return array<string, mixed>
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function getTokenByID(string $token_id): array
    {
        return $this->manager->request('/wallet/getassetissuebyid', [
            'value' => $token_id,
        ]);
    }
}
