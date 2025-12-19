<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Service;

use Tourze\TronAPI\Exception\InvalidArgumentException;
use Tourze\TronAPI\Exception\RuntimeException;
use Tourze\TronAPI\Tron;
use Tourze\TronAPI\ValueObject\AbiFunction;
use Tourze\TronAPI\ValueObject\ContractCallResult;
use Tourze\Web3PHP\Contracts\Ethabi;
use Tourze\Web3PHP\Contracts\Types\Address;
use Tourze\Web3PHP\Contracts\Types\Boolean;
use Tourze\Web3PHP\Contracts\Types\Bytes;
use Tourze\Web3PHP\Contracts\Types\DynamicBytes;
use Tourze\Web3PHP\Contracts\Types\Integer;
use Tourze\Web3PHP\Contracts\Types\Str;
use Tourze\Web3PHP\Contracts\Types\Uinteger;

/**
 * 智能合约服务类
 * 负责处理智能合约的触发和常量调用操作
 */
class SmartContractService
{
    protected Tron $tron;

    public function __construct(Tron $tron)
    {
        $this->tron = $tron;
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
        int $bandwidthLimit = 0,
    ): array {
        $func_abi = $this->findFunctionInAbi($abi, $function);
        $this->validateSmartContractParams($params, $func_abi, $feeLimit);
        $eth_abi = $this->createEthabi();

        $result = $this->triggerSmartContractRaw($func_abi, $eth_abi, $contract, $address, $feeLimit, $params, $callValue, $bandwidthLimit);

        return $this->processSmartContractResult($result, $func_abi, $eth_abi);
    }

    /**
     * 触发智能合约（返回原始响应）
     *
     * @param AbiFunction $func_abi
     * @param Ethabi $eth_abi
     * @param string $contract
     * @param string $address
     * @param int $feeLimit
     * @param array<string|int, mixed> $params
     * @param int $callValue
     * @param int $bandwidthLimit
     * @return mixed
     */
    private function triggerSmartContractRaw(
        AbiFunction $func_abi,
        Ethabi $eth_abi,
        string $contract,
        string $address,
        int $feeLimit,
        array $params,
        int $callValue = 0,
        int $bandwidthLimit = 0,
    ): mixed {
        $signature = $func_abi->buildSignature();
        /** @var array<int, mixed> $indexedParams */
        $indexedParams = array_values($params);
        $parameters = substr($eth_abi->encodeParameters($func_abi->toArray(), $indexedParams), 2);

        return $this->tron->getManager()->request('wallet/triggersmartcontract', [
            'contract_address' => $contract,
            'function_selector' => $signature,
            'parameter' => $parameters,
            'owner_address' => $address,
            'fee_limit' => $feeLimit,
            'call_value' => $callValue,
            'consume_user_resource_percent' => $bandwidthLimit,
        ]);
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
        string $address = '410000000000000000000000000000000000000000',
    ): array {
        $func_abi = $this->findFunctionInAbi($abi, $function);
        $this->validateConstantContractParams($params, $func_abi);
        $eth_abi = $this->createEthabi();

        $result = $this->triggerConstantContractRaw($func_abi, $eth_abi, $contract, $address, $params);

        return $this->processConstantContractResult($result, $func_abi, $eth_abi);
    }

    /**
     * 触发常量合约（返回原始响应）
     *
     * @param AbiFunction $func_abi
     * @param Ethabi $eth_abi
     * @param string $contract
     * @param string $address
     * @param array<string|int, mixed> $params
     * @return mixed
     */
    private function triggerConstantContractRaw(
        AbiFunction $func_abi,
        Ethabi $eth_abi,
        string $contract,
        string $address,
        array $params,
    ): mixed {
        $signature = $func_abi->buildSignature();
        /** @var array<int, mixed> $indexedParams */
        $indexedParams = array_values($params);
        $parameters = substr($eth_abi->encodeParameters($func_abi->toArray(), $indexedParams), 2);

        return $this->tron->getManager()->request('wallet/triggerconstantcontract', [
            'contract_address' => $contract,
            'function_selector' => $signature,
            'parameter' => $parameters,
            'owner_address' => $address,
        ]);
    }

    /**
     * 触发智能合约（返回 VO 对象）
     *
     * @param array<int, array<string, mixed>>|string $abi ABI definition (array or JSON string)
     * @param string $contract       $tron->toHex('Txxxxx');
     * @param string $function
     * @param array<string|int, mixed> $params         array("0"=>$value);
     * @param int    $feeLimit
     * @param string $address        $tron->toHex('Txxxxx');
     * @param int    $callValue
     * @param int    $bandwidthLimit
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function triggerSmartContractVO(
        array|string $abi,
        string $contract,
        string $function,
        array $params,
        int $feeLimit,
        string $address,
        int $callValue = 0,
        int $bandwidthLimit = 0,
    ): ContractCallResult {
        $func_abi = $this->findFunctionInAbi($abi, $function);
        $this->validateSmartContractParams($params, $func_abi, $feeLimit);
        $eth_abi = $this->createEthabi();

        $rawResult = $this->triggerSmartContractRaw($func_abi, $eth_abi, $contract, $address, $feeLimit, $params, $callValue, $bandwidthLimit);

        if (!is_array($rawResult)) {
            throw new InvalidArgumentException('Expected array result from contract call, got: ' . gettype($rawResult));
        }

        return ContractCallResult::fromArray($rawResult);
    }

    /**
     * 触发常量合约（返回 VO 对象）
     *
     * @param array<int, array<string, mixed>>|string $abi ABI definition (array or JSON string)
     * @param string $contract $tron->toHex('Txxxxx');
     * @param string $function
     * @param array<string|int, mixed> $params   array("0"=>$value);
     * @param string $address  $tron->toHex('Txxxxx');
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function triggerConstantContractVO(
        array|string $abi,
        string $contract,
        string $function,
        array $params = [],
        string $address = '410000000000000000000000000000000000000000',
    ): ContractCallResult {
        $func_abi = $this->findFunctionInAbi($abi, $function);
        $this->validateConstantContractParams($params, $func_abi);
        $eth_abi = $this->createEthabi();

        $rawResult = $this->triggerConstantContractRaw($func_abi, $eth_abi, $contract, $address, $params);

        if (!is_array($rawResult)) {
            throw new InvalidArgumentException('Expected array result from contract call, got: ' . gettype($rawResult));
        }

        return ContractCallResult::fromArray($rawResult);
    }

    /**
     * 从ABI中查找指定函数定义
     *
     * @param array<int, array<string, mixed>>|string $abi
     * @param string $function
     * @return AbiFunction
     * @throws InvalidArgumentException
     */
    private function findFunctionInAbi(array|string $abi, string $function): AbiFunction
    {
        // Parse JSON string if needed
        if (is_string($abi)) {
            $decoded = json_decode($abi, true);
            if (!is_array($decoded)) {
                throw new InvalidArgumentException('Invalid ABI JSON string');
            }
            $abi = $decoded;
        }

        // Search for function in ABI
        foreach ($abi as $item) {
            if (!is_array($item)) {
                continue;
            }

            if (isset($item['name']) && $item['name'] === $function) {
                /** @var array<string, mixed> $item */
                return AbiFunction::fromArray($item);
            }
        }

        throw new InvalidArgumentException("Function {$function} not defined in ABI");
    }

    /**
     * 验证智能合约参数
     *
     * @param array<string|int, mixed> $params
     * @param AbiFunction $func_abi
     * @param int $feeLimit
     * @throws InvalidArgumentException
     */
    private function validateSmartContractParams(array $params, AbiFunction $func_abi, int $feeLimit): void
    {
        if ($func_abi->getInputCount() !== count($params)) {
            throw new InvalidArgumentException('Count of params and abi inputs must be identical');
        }

        if ($feeLimit > 1000000000) {
            throw new InvalidArgumentException('fee_limit must not be greater than 1000000000');
        }
    }

    private function createEthabi(): Ethabi
    {
        return new Ethabi([
            'address' => new Address(),
            'bool' => new Boolean(),
            'bytes' => new Bytes(),
            'dynamicBytes' => new DynamicBytes(),
            'int' => new Integer(),
            'string' => new Str(),
            'uint' => new Uinteger(),
        ]);
    }

    /**
     * 处理智能合约执行结果
     *
     * @param mixed $result
     * @param AbiFunction $func_abi
     * @param Ethabi $eth_abi
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    private function processSmartContractResult(mixed $result, AbiFunction $func_abi, Ethabi $eth_abi): array
    {
        if (!is_array($result)) {
            throw new InvalidArgumentException('Expected array result from contract call, got: ' . gettype($result));
        }

        // Ensure all keys are strings
        /** @var array<string, mixed> $result */
        $result = $this->ensureStringKeys($result);

        $this->validateSmartContractResult($result);

        if (isset($result['result']) && is_array($result['result']) && isset($result['result']['result'])) {
            return $this->processSuccessfulContractResult($result, $func_abi, $eth_abi);
        }

        $this->throwContractExecutionError($result);
    }

    /**
     * 确保数组键为字符串
     *
     * @param array<mixed, mixed> $array
     * @return array<string, mixed>
     */
    private function ensureStringKeys(array $array): array
    {
        /** @var array<string, mixed> $result */
        $result = [];
        foreach ($array as $key => $value) {
            $stringKey = is_string($key) ? $key : (string) $key;
            $result[$stringKey] = $value;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $result
     * @throws InvalidArgumentException
     */
    private function validateSmartContractResult(array $result): void
    {
        if (!isset($result['result']) || !is_array($result['result'])) {
            throw new InvalidArgumentException('No result field in response. Raw response:' . print_r($result, true));
        }
    }

    /**
     * 处理成功的合约调用结果
     *
     * @param array<string, mixed> $result
     * @param AbiFunction $func_abi
     * @param Ethabi $eth_abi
     * @return array<string, mixed>
     */
    private function processSuccessfulContractResult(array $result, AbiFunction $func_abi, Ethabi $eth_abi): array
    {
        if ($func_abi->hasOutputs() && $this->hasConstantResult($result)) {
            if (!isset($result['constant_result']) || !is_array($result['constant_result'])) {
                throw new InvalidArgumentException('Expected constant_result array');
            }
            $constantResult = $result['constant_result'][0] ?? null;
            if (!is_string($constantResult)) {
                throw new InvalidArgumentException('Expected string constant_result, got: ' . gettype($constantResult));
            }
            $decoded = $eth_abi->decodeParameters($func_abi->toArray(), $constantResult);

            // decodeParameters always returns array according to its signature
            return $this->ensureStringKeys($decoded);
        }

        $transaction = $result['transaction'] ?? [];
        if (!is_array($transaction)) {
            throw new InvalidArgumentException('Expected array transaction, got: ' . gettype($transaction));
        }

        return $this->ensureStringKeys($transaction);
    }

    /**
     * 检查结果中是否包含常量结果
     *
     * @param array<string, mixed> $result
     * @return bool
     */
    private function hasConstantResult(array $result): bool
    {
        return isset($result['constant_result'])
            && is_array($result['constant_result'])
            && isset($result['constant_result'][0]);
    }

    /**
     * 抛出合约执行错误异常
     *
     * @param array<string, mixed> $result
     * @return never
     * @throws RuntimeException
     */
    private function throwContractExecutionError(array $result): never
    {
        $message = '';
        if (isset($result['result']) && is_array($result['result']) && isset($result['result']['message'])) {
            $rawMessage = $result['result']['message'];
            if (is_string($rawMessage)) {
                $message = $this->tron->hexString2Utf8($rawMessage);
            }
        }

        throw new RuntimeException('Failed to execute. Error:' . $message);
    }

    /**
     * 验证常量合约参数
     *
     * @param array<string|int, mixed> $params
     * @param AbiFunction $func_abi
     * @throws InvalidArgumentException
     */
    private function validateConstantContractParams(array $params, AbiFunction $func_abi): void
    {
        if ($func_abi->getInputCount() !== count($params)) {
            throw new InvalidArgumentException('Count of params and abi inputs must be identical');
        }
    }

    /**
     * 处理常量合约执行结果
     *
     * @param mixed $result
     * @param AbiFunction $func_abi
     * @param Ethabi $eth_abi
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    private function processConstantContractResult(mixed $result, AbiFunction $func_abi, Ethabi $eth_abi): array
    {
        // 常量合约结果处理与智能合约相同
        return $this->processSmartContractResult($result, $func_abi, $eth_abi);
    }
}
