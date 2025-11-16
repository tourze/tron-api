<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Service;

use Tourze\TronAPI\Exception\InvalidArgumentException;
use Tourze\TronAPI\Exception\RuntimeException;
use Tourze\TronAPI\Tron;
use Tourze\TronAPI\ValueObject\TokenOptions;

/**
 * Token管理服务类
 * 负责处理Token的创建、购买和更新操作
 */
class TokenService
{
    protected Tron $tron;

    public function __construct(Tron $tron)
    {
        $this->tron = $tron;
    }

    /**
     * 购买Token
     *
     * @param string $issuerAddress Token发行者地址
     * @param string $tokenID Token ID
     * @param int $amount 购买数量
     * @param string $buyer 购买者地址
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function purchaseToken(string $issuerAddress, string $tokenID, int $amount, string $buyer): array
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Invalid amount provided');
        }

        $purchase = $this->tron->getManager()->request('wallet/participateassetissue', [
            'to_address' => $this->tron->address2HexString($issuerAddress),
            'owner_address' => $this->tron->address2HexString($buyer),
            'asset_name' => $this->tron->stringUtf8toHex($tokenID),
            'amount' => $this->tron->toTron($amount),
        ]);

        if (array_key_exists('Error', $purchase)) {
            $errorMessage = is_string($purchase['Error']) ? $purchase['Error'] : 'Unknown error';
            throw new InvalidArgumentException($errorMessage);
        }

        return $purchase;
    }

    /**
     * 创建Token
     *
     * @param array<string, mixed> $options Token选项
     * @param string|null $issuerAddress 发行者地址
     *
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function createToken(array $options = [], ?string $issuerAddress = null): array
    {
        $startDate = new \DateTime();
        $startTimeStamp = $startDate->getTimestamp() * 1000;

        $normalizedOptions = $this->normalizeTokenOptions($options);
        if (is_null($issuerAddress)) {
            $issuerAddress = $this->tron->address['hex'];
        }

        $tokenOptions = TokenOptions::fromArray($normalizedOptions, $startTimeStamp);

        // At this point, issuerAddress is never null (either provided or defaulted)
        $data = $this->buildTokenData($tokenOptions->toArray(), $issuerAddress ?? '');

        return $this->tron->getManager()->request('wallet/createassetissue', $data);
    }

    /**
     * 更新Token
     *
     * @param string $description Token描述
     * @param string $url Token URL
     * @param int $freeBandwidth 免费带宽
     * @param int $freeBandwidthLimit 免费带宽限制
     * @param string|null $address 所有者地址
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function updateToken(string $description, string $url, int $freeBandwidth = 0, int $freeBandwidthLimit = 0, ?string $address = null): array
    {
        if (null === $address) {
            throw new InvalidArgumentException('Owner Address not specified');
        }

        if ($freeBandwidth < 0) {
            throw new InvalidArgumentException('Invalid free bandwidth amount provided');
        }

        // Validate bandwidth limit: must be non-negative
        // Note: Original logic was: $freeBandwidthLimit < 0 && ($freeBandwidth && !$freeBandwidthLimit)
        // which is logically impossible (always false). Keeping minimal validation.
        if ($freeBandwidthLimit < 0) {
            throw new InvalidArgumentException('Invalid free bandwidth limit provided');
        }

        return $this->tron->getManager()->request('wallet/updateasset', [
            'owner_address' => $this->tron->address2HexString($address),
            'description' => $this->tron->stringUtf8toHex($description),
            'url' => $this->tron->stringUtf8toHex($url),
            'new_limit' => intval($freeBandwidth),
            'new_public_limit' => intval($freeBandwidthLimit),
        ]);
    }

    /**
     * 规范化Token选项，填充默认值
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function normalizeTokenOptions(array $options): array
    {
        $defaults = [
            'totalSupply' => 0,
            'trxRatio' => 1,
            'tokenRatio' => 1,
            'freeBandwidth' => 0,
            'freeBandwidthLimit' => 0,
            'frozenAmount' => 0,
            'frozenDuration' => 0,
        ];

        foreach ($defaults as $key => $defaultValue) {
            if (!isset($options[$key])) {
                $options[$key] = $defaultValue;
            }
        }

        return $options;
    }

    /**
     * 构建Token创建请求数据
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function buildTokenData(array $options, string $issuerAddress): array
    {
        // Helper to safely get string values
        $getString = function (string $key) use ($options): string {
            $value = $options[$key] ?? '';
            if (is_string($value)) {
                return $value;
            }
            if (is_scalar($value)) {
                return (string) $value;
            }

            return '';
        };

        // Helper to safely get int values
        $getInt = function (string $key, int $default = 0) use ($options): int {
            $value = $options[$key] ?? $default;
            if (is_int($value)) {
                return $value;
            }
            if (is_numeric($value)) {
                return (int) $value;
            }

            return $default;
        };

        $data = [
            'owner_address' => $this->tron->address2HexString($issuerAddress),
            'name' => $this->tron->stringUtf8toHex($getString('name')),
            'abbr' => $this->tron->stringUtf8toHex($getString('abbreviation')),
            'description' => $this->tron->stringUtf8toHex($getString('description')),
            'url' => $this->tron->stringUtf8toHex($getString('url')),
            'total_supply' => $getInt('totalSupply'),
            'trx_num' => $getInt('trxRatio'),
            'num' => $getInt('tokenRatio'),
            'start_time' => $getInt('saleStart'),
            'end_time' => $getInt('saleEnd'),
            'free_asset_net_limit' => $getInt('freeBandwidth'),
            'public_free_asset_net_limit' => $getInt('freeBandwidthLimit'),
            'frozen_supply' => [
                'frozen_amount' => $getInt('frozenAmount'),
                'frozen_days' => $getInt('frozenDuration'),
            ],
        ];

        if (isset($options['precision'])) {
            $precision = $getInt('precision');
            if (!is_nan($precision)) {
                $data['precision'] = $precision;
            }
        }

        if (isset($options['voteScore'])) {
            $voteScore = $getInt('voteScore');
            if (!is_nan($voteScore)) {
                $data['vote_score'] = $voteScore;
            }
        }

        return $data;
    }
}
