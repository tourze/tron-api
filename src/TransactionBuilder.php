<?php

declare(strict_types=1);

namespace Tourze\TronAPI;

use Tourze\TronAPI\Exception\InvalidArgumentException;
use Tourze\TronAPI\Exception\RuntimeException;
use Tourze\TronAPI\Service\ContractManagementService;
use Tourze\TronAPI\Service\ResourceService;
use Tourze\TronAPI\Service\SmartContractService;
use Tourze\TronAPI\Service\TokenQueryService;
use Tourze\TronAPI\Service\TokenService;
use Tourze\TronAPI\Service\TransferService;

// Web3 插件

class TransactionBuilder
{
    /**
     * Tron 基础对象
     */
    protected Tron $tron;

    protected TransferService $transferService;

    protected ResourceService $resourceService;

    protected TokenService $tokenService;

    protected ContractManagementService $contractService;

    protected SmartContractService $smartContractService;

    protected TokenQueryService $tokenQueryService;

    /**
     * 创建 TransactionBuilder 对象
     */
    public function __construct(Tron $tron)
    {
        $this->tron = $tron;

        // 初始化服务层
        $this->transferService = new TransferService($tron);
        $this->resourceService = new ResourceService($tron);
        $this->tokenService = new TokenService($tron);
        $this->contractService = new ContractManagementService($tron);
        $this->smartContractService = new SmartContractService($tron);
        $this->tokenQueryService = new TokenQueryService($tron, $this->smartContractService);
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
        return $this->transferService->sendTrx($to, $amount, $from, $message);
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
        return $this->transferService->sendToken($to, $amount, $tokenID, $from);
    }

    /**
     * 购买 Token
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
        return $this->tokenService->purchaseToken($issuerAddress, $tokenID, $amount, $buyer);
    }

    /**
     * 创建 Token
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
        return $this->tokenService->createToken($options, $issuerAddress);
    }

    /**
     * 冻结一定数量的 TRX
     * 向冻结 Token 的所有者提供带宽或能量以及 TRON Power（投票权）
     *
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function freezeBalance(float $amount = 0, int $duration = 3, string $resource = 'BANDWIDTH', ?string $address = null): array
    {
        return $this->resourceService->freezeBalance($amount, $duration, $resource, $address);
    }

    /**
     * 解冻已通过最小冻结期限的 TRX
     * 解冻将移除带宽和 TRON Power
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    public function unfreezeBalance(string $resource = 'BANDWIDTH', ?string $owner_address = null): array
    {
        return $this->resourceService->unfreezeBalance($resource, $owner_address);
    }

    /**
     * 更新 Token
     *
     * @param string|null $address
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function updateToken(string $description, string $url, int $freeBandwidth = 0, int $freeBandwidthLimit = 0, ?string $address = null): array
    {
        return $this->tokenService->updateToken($description, $url, $freeBandwidth, $freeBandwidthLimit, $address);
    }

    /**
     * 更新能量限制
     *
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function updateEnergyLimit(string $contractAddress, int $originEnergyLimit, string $ownerAddress): array
    {
        return $this->contractService->updateEnergyLimit($contractAddress, $originEnergyLimit, $ownerAddress);
    }

    /**
     * 更新设置
     *
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function updateSetting(string $contractAddress, int $userFeePercentage, string $ownerAddress): array
    {
        return $this->contractService->updateSetting($contractAddress, $userFeePercentage, $ownerAddress);
    }

    /**
     * 获取指定地址的所有TRC20 Token余额
     *
     * @param string $address 查询地址
     * @return array<int, array<string, mixed>> TRC20 Token余额列表
     */
    public function contractbalance(string $address): array
    {
        return $this->tokenQueryService->contractbalance($address);
    }

    /**
     * 触发智能合约
     *
     * @param array<int, array<string, mixed>>|string $abi ABI definition (array or JSON string)
     * @param string $contract       $tron->toHex('Txxxxx');
     * @param string $function
     * @param array<string|int, mixed> $params         array("0"=>$value);
     * @param int    $feeLimit
     * @param string $address        $tron->toHex('Txxxxx');
     * @param int    $callValue
     * @param int    $bandwidthLimit
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function triggerSmartContract(
        array|string $abi,
        string $contract,
        string $function,
        array $params,
        int $feeLimit,
        string $address,
        int $callValue = 0,
        int $bandwidthLimit = 0
    ): array {
        return $this->smartContractService->triggerSmartContract(
            $abi,
            $contract,
            $function,
            $params,
            $feeLimit,
            $address,
            $callValue,
            $bandwidthLimit
        );
    }

    /**
     * 触发常量合约
     *
     * @param array<int, array<string, mixed>>|string $abi ABI definition (array or JSON string)
     * @param string $contract $tron->toHex('Txxxxx');
     * @param string $function
     * @param array<string|int, mixed> $params   array("0"=>$value);
     * @param string $address  $tron->toHex('Txxxxx');
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function triggerConstantContract(
        array|string $abi,
        string $contract,
        string $function,
        array $params = [],
        string $address = '410000000000000000000000000000000000000000'
    ): array {
        return $this->smartContractService->triggerConstantContract(
            $abi,
            $contract,
            $function,
            $params,
            $address
        );
    }
}
