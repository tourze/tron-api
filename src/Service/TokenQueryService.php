<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Service;

use Tourze\TronAPI\Exception\InvalidArgumentException;
use Tourze\TronAPI\Exception\RuntimeException;
use Tourze\TronAPI\Tron;
use Tourze\TronAPI\ValueObject\TRC20BalanceInfo;

/**
 * Token查询服务类
 * 负责处理TRC20 Token余额查询等操作
 */
class TokenQueryService
{
    protected Tron $tron;

    protected SmartContractService $contractService;

    public function __construct(Tron $tron, SmartContractService $contractService)
    {
        $this->tron = $tron;
        $this->contractService = $contractService;
    }

    /**
     * 获取指定地址的所有TRC20 Token余额
     *
     * @param string $address 查询地址
     * @return array<int, array<string, mixed>> TRC20 Token余额列表
     */
    public function contractbalance(string $address): array
    {
        $abi = $this->getTRC20StandardAbi();
        $tokenData = $this->fetchTRC20TokenList();

        if (!$this->isValidTokenData($tokenData) || !$this->isValidAbi($abi)) {
            return [];
        }

        // Type narrowing: after validation checks, we know these are arrays with required keys
        assert(isset($tokenData['trc20_tokens']) && is_array($tokenData['trc20_tokens']));
        assert(isset($abi['entrys']) && is_array($abi['entrys']));

        /** @var array<int, mixed> $tokens */
        $tokens = array_values($tokenData['trc20_tokens']);
        /** @var array<int, mixed> $abiEntries */
        $abiEntries = array_values($abi['entrys']);

        return $this->calculateTokenBalances($address, $tokens, $abiEntries);
    }

    /**
     * 获取指定地址的所有TRC20 Token余额（返回 VO 对象数组）
     *
     * @param string $address 查询地址
     * @return array<int, TRC20BalanceInfo> TRC20 Token余额列表
     */
    public function contractbalanceVO(string $address): array
    {
        $balances = $this->contractbalance($address);

        return array_map(
            fn (array $balance) => TRC20BalanceInfo::fromArray($balance),
            $balances
        );
    }

    /**
     * 获取TRC20标准ABI
     *
     * @return array<string, mixed>
     */
    protected function getTRC20StandardAbi(): array
    {
        $abiJson = '{"entrys": [{"constant": true,"name": "name","outputs": [{"type": "string"}],"type": "Function","stateMutability": "View"},{"name": "approve","inputs": [{"name": "_spender","type": "address"},{"name": "_value","type": "uint256"}],"outputs": [{"type": "bool"}],"type": "Function","stateMutability": "Nonpayable"},{"name": "setCanApproveCall","inputs": [{"name": "_val","type": "bool"}],"type": "Function","stateMutability": "Nonpayable"},{"constant": true,"name": "totalSupply","outputs": [{"type": "uint256"}],"type": "Function","stateMutability": "View"},{"name": "transferFrom","inputs": [{"name": "_from","type": "address"},{"name": "_to","type": "address"},{"name": "_value","type": "uint256"}],"outputs": [{"type": "bool"}],"type": "Function","stateMutability": "Nonpayable"},{"constant": true,"name": "decimals","outputs": [{"type": "uint8"}],"type": "Function","stateMutability": "View"},{"name": "setCanBurn","inputs": [{"name": "_val","type": "bool"}],"type": "Function","stateMutability": "Nonpayable"},{"name": "burn","inputs": [{"name": "_value","type": "uint256"}],"outputs": [{"name": "success","type": "bool"}],"type": "Function","stateMutability": "Nonpayable"},{"constant": true,"name": "balanceOf","inputs": [{"name": "_owner","type": "address"}],"outputs": [{"type": "uint256"}],"type": "Function","stateMutability": "View"},{"constant": true,"name": "symbol","outputs": [{"type": "string"}],"type": "Function","stateMutability": "View"},{"name": "transfer","inputs": [{"name": "_to","type": "address"},{"name": "_value","type": "uint256"}],"outputs": [{"type": "bool"}],"type": "Function","stateMutability": "Nonpayable"},{"constant": true,"name": "canBurn","outputs": [{"type": "bool"}],"type": "Function","stateMutability": "View"},{"name": "approveAndCall","inputs": [{"name": "_spender","type": "address"},{"name": "_value","type": "uint256"},{"name": "_extraData","type": "bytes"}],"outputs": [{"name": "success","type": "bool"}],"type": "Function","stateMutability": "Nonpayable"},{"constant": true,"name": "allowance","inputs": [{"name": "_owner","type": "address"},{"name": "_spender","type": "address"}],"outputs": [{"type": "uint256"}],"type": "Function","stateMutability": "View"},{"name": "transferOwnership","inputs": [{"name": "_newOwner","type": "address"}],"type": "Function","stateMutability": "Nonpayable"},{"constant": true,"name": "canApproveCall","outputs": [{"type": "bool"}],"type": "Function","stateMutability": "View"},{"type": "Constructor","stateMutability": "Nonpayable"},{"name": "Transfer","inputs": [{"indexed": true,"name": "_from","type": "address"},{"indexed": true,"name": "_to","type": "address"},{"name": "_value","type": "uint256"}],"type": "Event"},{"name": "Approval","inputs": [{"indexed": true,"name": "_owner","type": "address"},{"indexed": true,"name": "_spender","type": "address"},{"name": "_value","type": "uint256"}],"type": "Event"},{"name": "Burn","inputs": [{"indexed": true,"name": "_from","type": "address"},{"name": "_value","type": "uint256"}],"type": "Event"}]}';

        return json_decode($abiJson, true);
    }

    /**
     * 从TronScan API获取TRC20 Token列表
     *
     * @return array<string, mixed>|null
     */
    protected function fetchTRC20TokenList(): ?array
    {
        $url = 'https://apilist.tronscan.org/api/token_trc20?sort=issue_time&limit=100&start=0';
        $response = @file_get_contents($url);

        if (false === $response) {
            return null;
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return null;
        }

        /** @var array<string, mixed> $result */
        $result = $decoded;

        return $result;
    }

    /**
     * 验证Token数据有效性
     *
     * @param array<string, mixed>|null $tokenData
     */
    private function isValidTokenData(?array $tokenData): bool
    {
        return null !== $tokenData
            && isset($tokenData['trc20_tokens'])
            && is_array($tokenData['trc20_tokens']);
    }

    /**
     * 验证ABI数据有效性
     *
     * @param array<string, mixed>|null $abi
     */
    private function isValidAbi(?array $abi): bool
    {
        return null !== $abi
            && isset($abi['entrys'])
            && is_array($abi['entrys']);
    }

    /**
     * 计算所有Token的余额
     *
     * @param string $address
     * @param array<int, mixed> $tokens
     * @param array<int, mixed> $abiEntries
     * @return array<int, array<string, mixed>>
     */
    private function calculateTokenBalances(string $address, array $tokens, array $abiEntries): array
    {
        $balances = [];

        // Narrow type for ABI entries - validated by isValidAbi() before this call
        /** @var array<int, array<string, mixed>> $typedAbiEntries */
        $typedAbiEntries = $abiEntries;

        foreach ($tokens as $token) {
            if (!$this->isValidToken($token)) {
                continue;
            }

            // After isValidToken check, we know $token is array<string, mixed>
            assert(is_array($token));
            /** @var array<string, mixed> $tokenData */
            $tokenData = $token;

            $balance = $this->getTokenBalance($address, $tokenData, $typedAbiEntries);
            if (null !== $balance) {
                $balances[] = $balance;
            }
        }

        return $balances;
    }

    /**
     * 验证Token数据结构
     *
     * @param mixed $token
     */
    private function isValidToken($token): bool
    {
        return is_array($token)
            && isset($token['contract_address'], $token['decimals'], $token['name'], $token['symbol']);
    }

    /**
     * 获取单个Token的余额信息
     *
     * @param string $address
     * @param array<string, mixed> $token
     * @param array<int, array<string, mixed>> $abiEntries
     * @return array<string, mixed>|null
     */
    private function getTokenBalance(string $address, array $token, array $abiEntries): ?array
    {
        // Ensure token has required fields
        assert(isset($token['contract_address']) && is_string($token['contract_address']));

        $result = $this->callBalanceOf($address, $token['contract_address'], $abiEntries);
        if (!isset($result['0'])) {
            return null;
        }

        $balanceData = $result['0'];
        $calculatedBalance = $this->calculateBalance($balanceData, $token['decimals']);
        if (null === $calculatedBalance || $calculatedBalance <= 0) {
            return null;
        }

        $value = $this->extractValueFromBalanceData($balanceData);

        return $this->buildTokenBalanceResult($token, $calculatedBalance, $value);
    }

    /**
     * 调用智能合约的 balanceOf 方法
     *
     * @param string $address
     * @param string $contractAddress
     * @param array<int, array<string, mixed>> $abiEntries
     * @return array<string, mixed>
     */
    private function callBalanceOf(string $address, string $contractAddress, array $abiEntries): array
    {
        // PHPStan expects array<string, mixed> for $params parameter
        $params = ['address' => $this->tron->toHex($address)];
        return $this->contractService->triggerSmartContract(
            $abiEntries,
            $this->tron->toHex($contractAddress),
            'balanceOf',
            $params,
            1000000,
            $this->tron->toHex($address),
            0,
            0
        );
    }

    /**
     * 从余额数据中提取值
     *
     * @param mixed $balanceData
     * @return string
     */
    private function extractValueFromBalanceData(mixed $balanceData): string
    {
        if (is_object($balanceData) && property_exists($balanceData, 'value')) {
            // Type-safe conversion: validate before casting
            $rawValue = $balanceData->value;
            return (is_string($rawValue) || is_numeric($rawValue)) ? (string) $rawValue : '';
        }

        if (is_array($balanceData) && isset($balanceData['value'])) {
            // Type-safe conversion: only convert if value is string or numeric
            $rawValue = $balanceData['value'];
            return (is_string($rawValue) || is_numeric($rawValue)) ? (string) $rawValue : '';
        }

        return '';
    }

    /**
     * 构建代币余额结果
     *
     * @param array<string, mixed> $token
     * @param float $balance
     * @param string $value
     * @return array<string, mixed>
     */
    private function buildTokenBalanceResult(array $token, float $balance, string $value): array
    {
        return [
            'name' => $token['name'],
            'symbol' => $token['symbol'],
            'balance' => $balance,
            'value' => $value,
            'decimals' => $token['decimals'],
        ];
    }

    /**
     * 计算实际余额
     *
     * @param mixed $balanceHex
     * @param mixed $decimals
     */
    private function calculateBalance($balanceHex, $decimals): ?float
    {
        if (!is_object($balanceHex) || !property_exists($balanceHex, 'value')) {
            return null;
        }

        $value = $balanceHex->value;
        if (!is_numeric($value) || !is_numeric($decimals)) {
            return null;
        }

        return 0 + (float) number_format(
            (float) $value / pow(10, (int) $decimals),
            (int) $decimals,
            '.',
            ''
        );
    }
}
