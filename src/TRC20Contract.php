<?php

/**
 * TronAPI
 *
 * @author  Shamsudin Serderov <steein.shamsudin@gmail.com>
 * @license https://github.com/iexbase/tron-api/blob/master/LICENSE (MIT License)
 *
 * 版本 1.3.4
 *
 * @see    https://github.com/iexbase/tron-api
 *
 * 完整的版权和许可信息请查看 LICENSE 文件
 *
 * @return array<string, mixed>
 */

declare(strict_types=1);

namespace Tourze\TronAPI;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Tourze\TronAPI\Exception\TRC20Exception;
use Tourze\TronAPI\Exception\TronException;
use Tourze\TronAPI\ValueObject\TokenBalance;
use Tourze\TronAPI\ValueObject\TokenMetadata;
use Tourze\TronAPI\ValueObject\TransactionResult;

/**
 * TRC20 合约类
 *
 * @return array<string, mixed>
 */
class TRC20Contract
{
    public const TRX_TO_SUN = 1000000;

    /*
     * Token 支持的最大小数位数
     *
     * @var int|null
     */
    private ?int $_decimals = null;

    /*
     * Token 名称
     *
     * @var string|null
     *
     * @return array<string, mixed>
     */
    private ?string $_name = null;

    /*
     * Token 符号
     *
     * @var string|null
     *
     * @return array<string, mixed>
     */
    private ?string $_symbol = null;

    /**
     * 发行 TRC20 Token 的智能合约地址
     *
     * @return array<string, mixed>
     */
    private string $contractAddress;

    /**
     * ABI 数据
     *
     * @var array<int, array<string, mixed>>|null
     *
     * @return array<string, mixed>
     */
    private ?array $abiData = null;

    /**
     * 费用限额
     *
     * @return array<string, mixed>
     */
    private int $feeLimit = 10;

    /**
     * Tron 基础对象
     *
     * @return array<string, mixed>
     */
    protected Tron $_tron;

    /**
     * 总供应量
     *
     * @var string|null
     */
    private ?string $_totalSupply = null;

    /**
     * 创建 TRC20 合约实例
     *
     * @return array<string, mixed>
     */
    public function __construct(Tron $tron, string $contractAddress, ?string $abi = null)
    {
        $this->_tron = $tron;

        // 如果 abi 不存在，则使用默认值
        if (is_null($abi)) {
            $abiContent = file_get_contents(__DIR__ . '/trc20.json');
            $abi = is_string($abiContent) ? $abiContent : '';
        }

        $decoded = json_decode($abi, true);
        /** @var array<int, array<string, mixed>>|null $abiData */
        $abiData = is_array($decoded) ? $decoded : null;
        $this->abiData = $abiData;
        $this->contractAddress = $contractAddress;
    }

    /**
     * 调试信息
     *
     * @return array<string, mixed>
     * @throws TronException
     */
    public function __debugInfo(): array
    {
        return $this->array();
    }

    /**
     * 清除缓存值
     */
    public function clearCached(): void
    {
        $this->_name = null;
        $this->_symbol = null;
        $this->_decimals = null;
        $this->_totalSupply = null;
    }

    /**
     * 获取全部数据
     *
     * @return array<string, mixed>
     * @throws TronException
     */
    public function array(): array
    {
        return [
            'name' => $this->name(),
            'symbol' => $this->symbol(),
            'decimals' => $this->decimals(),
            'totalSupply' => $this->totalSupply(true),
        ];
    }

    /**
     * 获取Token元数据（VO对象）
     *
     * @throws TronException
     */
    public function getMetadata(): TokenMetadata
    {
        return new TokenMetadata(
            name: $this->name(),
            symbol: $this->symbol(),
            decimals: $this->decimals(),
            totalSupply: $this->_totalSupply ?? $this->totalSupply(false), // 使用原始值
        );
    }

    /**
     * 获取 Token 名称
     *
     * @throws TronException
     */
    public function name(): string
    {
        if (null !== $this->_name) {
            return $this->_name;
        }

        $result = $this->trigger('name', null, []);
        $name = $result[0] ?? null;

        if (!is_string($name)) {
            throw new TRC20Exception('Failed to retrieve TRC20 token name');
        }

        $this->_name = $this->cleanStr($name);

        return $this->_name;
    }

    /**
     * 获取 Token 符号
     *
     * @throws TronException
     */
    public function symbol(): string
    {
        if (null !== $this->_symbol) {
            return $this->_symbol;
        }
        $result = $this->trigger('symbol', null, []);
        $code = $result[0] ?? null;

        if (!is_string($code)) {
            throw new TRC20Exception('Failed to retrieve TRRC20 token symbol');
        }

        $this->_symbol = $this->cleanStr($code);

        return $this->_symbol;
    }

    /**
     * 获取在主网发行的 Token 总数量
     *
     * @throws TronException
     * @throws TRC20Exception
     */
    public function totalSupply(bool $scaled = true): string
    {
        if (null === $this->_totalSupply) {
            $result = $this->trigger('totalSupply', null, []);
            $totalSupplyValue = $result[0] ?? null;

            if (!is_object($totalSupplyValue) || !method_exists($totalSupplyValue, 'toString')) {
                throw new TRC20Exception('Failed to retrieve TRC20 token totalSupply');
            }

            $totalSupply = $totalSupplyValue->toString();

            if (!is_string($totalSupply)) {
                throw new TRC20Exception('Invalid TRC20 token totalSupply format');
            }

            $matchResult = preg_match('/^[0-9]+$/', $totalSupply);
            if (1 !== $matchResult) {
                throw new TRC20Exception('Invalid TRC20 token totalSupply format');
            }

            $this->_totalSupply = $totalSupply;
        }

        return $scaled ? $this->decimalValue($this->_totalSupply, $this->decimals()) : $this->_totalSupply;
    }

    /**
     * 获取 Token 支持的最大小数位数
     *
     * @throws TRC20Exception
     * @throws TronException
     */
    public function decimals(): int
    {
        if (null !== $this->_decimals) {
            return $this->_decimals;
        }

        $result = $this->trigger('decimals', null, []);
        $scaleValue = $result[0] ?? null;

        if (!is_object($scaleValue) || !method_exists($scaleValue, 'toString')) {
            throw new TRC20Exception('Failed to retrieve TRC20 token decimals/scale value');
        }

        $scaleString = $scaleValue->toString();
        $scale = is_numeric($scaleString) ? (int) $scaleString : 0;

        if ($scale < 0) {
            throw new TRC20Exception('Invalid TRC20 token decimals/scale value');
        }

        $this->_decimals = $scale;

        return $this->_decimals;
    }

    /**
     * 查询 TRC20 合约余额（返回字符串，向后兼容）
     *
     * @throws TRC20Exception
     * @throws TronException
     */
    public function balanceOf(?string $address = null, bool $scaled = true): string
    {
        $balanceVO = $this->getBalance($address);

        return $scaled ? $balanceVO->getScaled() : $balanceVO->getRaw();
    }

    /**
     * 查询 TRC20 合约余额（返回VO对象）
     *
     * @throws TRC20Exception
     * @throws TronException
     */
    public function getBalance(?string $address = null): TokenBalance
    {
        if (is_null($address)) {
            $address = $this->_tron->address['base58'];
        }

        // Ensure address is not null
        assert(null !== $address);

        $addr = str_pad($this->_tron->address2HexString($address), 64, '0', STR_PAD_LEFT);
        $result = $this->trigger('balanceOf', $address, $this->toStringKeyedArray(['0' => $addr]));
        $balanceValue = $result[0] ?? null;

        if (!is_object($balanceValue) || !method_exists($balanceValue, 'toString')) {
            throw new TRC20Exception(sprintf('Failed to retrieve TRC20 token balance of address "%s"', $addr));
        }

        $balance = $balanceValue->toString();

        if (!is_string($balance)) {
            throw new TRC20Exception(sprintf('Failed to retrieve TRC20 token balance of address "%s"', $addr));
        }

        $matchResult = preg_match('/^[0-9]+$/', $balance);
        if (1 !== $matchResult) {
            throw new TRC20Exception(sprintf('Failed to retrieve TRC20 token balance of address "%s"', $addr));
        }

        return TokenBalance::fromRaw($balance, $this->decimals());
    }

    /**
     * 发送 TRC20 合约交易（返回数组，向后兼容）
     *
     * @return array<string, mixed>
     * @throws TRC20Exception
     * @throws TronException
     */
    public function transfer(string $to, string $amount, ?string $from = null): array
    {
        return $this->sendTransfer($to, $amount, $from)->toArray();
    }

    /**
     * 发送 TRC20 合约交易（返回VO对象）
     *
     * @throws TRC20Exception
     * @throws TronException
     */
    public function sendTransfer(string $to, string $amount, ?string $from = null): TransactionResult
    {
        if (null === $from) {
            $from = $this->_tron->address['base58'];
        }

        if ($this->feeLimit <= 0) {
            throw new TRC20Exception('fee_limit is required.');
        }
        if ($this->feeLimit > 1000) {
            throw new TRC20Exception('fee_limit must not be greater than 1000 TRX.');
        }

        // Convert to numeric strings for bcmath
        $feeStr = sprintf('%d', $this->feeLimit);
        $sunStr = sprintf('%d', self::TRX_TO_SUN);
        $feeLimitInSun = bcmul($feeStr, $sunStr);

        // Calculate token amount with proper precision
        $decimals = $this->decimals();
        $powerOf10 = str_pad('1', $decimals + 1, '0');
        // Ensure numeric strings for bcmul
        assert(is_numeric($amount) && is_numeric($powerOf10));
        $tokenAmount = bcmul($amount, $powerOf10, 0);

        // Ensure from is not null
        assert(null !== $from);

        // Ensure abiData is not null and is an array
        if (null === $this->abiData) {
            throw new TRC20Exception('ABI data is not loaded');
        }

        $transfer = $this->_tron->getTransactionBuilder()
            ->triggerSmartContract(
                $this->abiData,
                $this->_tron->address2HexString($this->contractAddress),
                'transfer',
                $this->toStringKeyedArray(['0' => $this->_tron->address2HexString($to), '1' => $tokenAmount]),
                (int) $feeLimitInSun,
                $this->_tron->address2HexString($from)
            )
        ;

        $signedTransaction = $this->_tron->signTransaction($transfer);
        $response = $this->_tron->sendRawTransaction($signedTransaction);

        $mergedResponse = array_merge($response, $signedTransaction);

        return TransactionResult::fromArray($mergedResponse);
    }

    /**
     * 获取 TRC20 全部交易
     *
     * @return array<string, mixed>
     * @throws TronException
     */
    public function getTransactions(string $address, int $limit = 100): array
    {
        return $this->_tron->getManager()
            ->request("v1/accounts/{$address}/transactions/trc20?limit={$limit}&contract_address={$this->contractAddress}", [], 'get')
        ;
    }

    /**
     * 根据合约地址获取交易信息
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     * @throws TronException
     */
    public function getTransactionInfoByContract(array $options = []): array
    {
        return $this->_tron->getManager()
            ->request("v1/contracts/{$this->contractAddress}/transactions?" . http_build_query($options), [], 'get')
        ;
    }

    /**
     * 获取 TRC20 Token 持有者余额
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     * @throws TronException
     */
    public function getTRC20TokenHolderBalance(array $options = []): array
    {
        return $this->_tron->getManager()
            ->request("v1/contracts/{$this->contractAddress}/tokens?" . http_build_query($options), [], 'get')
        ;
    }

    /**
     * 查找交易
     *
     * @return array<string, mixed>
     * @throws TronException
     */
    public function getTransaction(string $transaction_id): array
    {
        return $this->_tron->getManager()
            ->request('/wallet/gettransactioninfobyid', ['value' => $transaction_id], 'post')
        ;
    }

    /**
     * 配置触发器
     *
     * @param string|null $address
     * @param string $function
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     * @throws TronException
     */
    private function trigger(string $function, ?string $address = null, array $params = [])
    {
        // Ensure abiData is not null
        if (null === $this->abiData) {
            throw new TRC20Exception('ABI data is not loaded');
        }

        $owner_address = is_null($address) ? '410000000000000000000000000000000000000000' : $this->_tron->address2HexString($address);

        return $this->_tron->getTransactionBuilder()
            ->triggerConstantContract($this->abiData, $this->_tron->address2HexString($this->contractAddress), $function, $params, $owner_address)
        ;
    }

    protected function decimalValue(string $int, int $scale = 18): string
    {
        return BigDecimal::of($int)
            ->dividedBy(BigDecimal::of(10)->power($scale), $scale, RoundingMode::DOWN)
            ->toScale($scale, RoundingMode::DOWN)
            ->__toString()
        ;
    }

    public function cleanStr(string $str): string
    {
        $result = preg_replace('/[^\w.-]/', '', trim($str));

        return is_string($result) ? $result : '';
    }

    /**
     * Helper method to convert array to array<string, mixed> for PHPStan
     *
     * @param array<int|string, mixed> $params
     * @return array<string, mixed>
     */
    private function toStringKeyedArray(array $params): array
    {
        $result = [];
        foreach ($params as $key => $value) {
            $result[(string) $key] = $value;
        }

        return $result;
    }

    /**
     * 设置费用限额
     */
    public function setFeeLimit(int $fee_limit): TRC20Contract
    {
        $this->feeLimit = $fee_limit;

        return $this;
    }
}
