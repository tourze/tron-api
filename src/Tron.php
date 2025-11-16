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
 */

declare(strict_types=1);

namespace Tourze\TronAPI;

use Elliptic\EC;
use Tourze\TronAPI\Exception\InvalidArgumentException;
use Tourze\TronAPI\Exception\RuntimeException;
use Tourze\TronAPI\Provider\HttpProviderInterface;
use Tourze\TronAPI\Service\BlockchainQueryService;
use Tourze\TronAPI\Service\CryptoService;
use Tourze\TronAPI\Service\EncodingService;
use Tourze\TronAPI\Support\Base58;
use Tourze\TronAPI\Support\Base58Check;
use Tourze\TronAPI\Support\Crypto;
use Tourze\TronAPI\Support\Hash;
use Tourze\TronAPI\Support\Keccak;
use Tourze\TronAPI\Support\Utils;
use Tourze\TronAPI\ValueObject\AccountInfo;
use Tourze\TronAPI\ValueObject\AddressValidation;
use Tourze\TronAPI\ValueObject\BlockInfo;
use Tourze\TronAPI\ValueObject\NodeInfo;
use Tourze\TronAPI\ValueObject\TransactionInfo;
use Tourze\TronAPI\ValueObject\TransactionReceipt;

/**
 * 用于与 Tron (TRX) 交互的 PHP API
 *
 * @author  Shamsudin Serderov <steein.shamsudin@gmail.com>
 *
 * @since   1.0.0
 */
class Tron implements TronInterface
{
    use TronAwareTrait;
    use Concerns\ManagesUniversal;
    use Concerns\ManagesTronscan;

    public const ADDRESS_SIZE = 34;
    public const ADDRESS_PREFIX = '41';
    public const ADDRESS_PREFIX_BYTE = 0x41;

    /**
     * 默认地址：
     * 示例：
     *      - base58:   T****
     *      - hex:      41****
     *
     * @var array<string, string|null>
     */
    public $address = [
        'base58' => null,
        'hex' => null,
    ];

    /**
     * 私钥
     *
     * @var string
     */
    public $privateKey;

    /**
     * 默认区块
     *
     * @var string|int|bool
     */
    protected $defaultBlock = 'latest';

    /**
     * 交易构建器
     *
     * @var TransactionBuilder
     */
    protected $transactionBuilder;

    /**
     * TRC20 合约实例
     *
     * @var TransactionBuilder
     */
    protected $trc20Contract;

    /**
     * 提供者管理器
     *
     * @var TronManager
     */
    protected $manager;

    /**
     * 结果对象模式标志
     *
     * @var bool
     */
    protected $isObject = false;

    /**
     * 服务层实例
     */
    protected BlockchainQueryService $blockchainService;

    protected CryptoService $cryptoService;

    protected EncodingService $encodingService;

    /**
     * 创建新的 Tron 对象
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function __construct(
        ?HttpProviderInterface $fullNode = null,
        ?HttpProviderInterface $solidityNode = null,
        ?HttpProviderInterface $eventServer = null,
        ?HttpProviderInterface $signServer = null,
        ?string $privateKey = null
    ) {
        if (!is_null($privateKey)) {
            $this->setPrivateKey($privateKey);
        }

        $this->setManager(new TronManager([
            'fullNode' => $fullNode,
            'solidityNode' => $solidityNode,
            'eventServer' => $eventServer,
            'signServer' => $signServer,
        ]));

        $this->transactionBuilder = new TransactionBuilder($this);

        // 初始化服务层
        $this->blockchainService = new BlockchainQueryService($this);
        $this->cryptoService = new CryptoService($this);
        $this->encodingService = new EncodingService();
    }

    /**
     * 创建新的 Tron 实例
     *
     * @return self
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public static function make(
        ?HttpProviderInterface $fullNode = null,
        ?HttpProviderInterface $solidityNode = null,
        ?HttpProviderInterface $eventServer = null,
        ?HttpProviderInterface $signServer = null,
        ?string $privateKey = null
    ): self {
        return new self($fullNode, $solidityNode, $eventServer, $signServer, $privateKey);
    }

    /**
     * Laravel 门面（Facade）
     */
    public function getFacade(): Tron
    {
        return $this;
    }

    /**
     * 设置管理器节点链接
     */
    public function setManager(TronManager $providers): void
    {
        $this->manager = $providers;
    }

    /**
     * 获取提供者管理器
     */
    public function getManager(): TronManager
    {
        return $this->manager;
    }

    /**
     * 合约模块
     *
     * @return TRC20Contract
     */
    public function contract(string $contractAddress, ?string $abi = null)
    {
        return new TRC20Contract($this, $contractAddress, $abi);
    }

    /**
     * 设置结果为对象模式
     *
     * @return Tron
     */
    public function setIsObject(bool $value)
    {
        $this->isObject = boolval($value);

        return $this;
    }

    /**
     * 获取交易构建器
     */
    public function getTransactionBuilder(): TransactionBuilder
    {
        return $this->transactionBuilder;
    }

    /**
     * 检查提供者是否有效
     * @param mixed $provider
     */
    public function isValidProvider($provider): bool
    {
        return $provider instanceof HttpProviderInterface;
    }

    /**
     * 设置默认区块
     *
     * @param string|int|bool $blockID
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function setDefaultBlock(string|int|bool $blockID = false): void
    {
        if (false === $blockID || 'latest' === $blockID || 'earliest' === $blockID || 0 === $blockID) {
            $this->defaultBlock = $blockID;

            return;
        }

        if (!is_int($blockID)) {
            throw new RuntimeException('Invalid block ID provided');
        }

        $this->defaultBlock = abs($blockID);
    }

    /**
     * 获取默认区块
     *
     * @return string|int|bool
     */
    public function getDefaultBlock()
    {
        return $this->defaultBlock;
    }

    /**
     * 设置私钥
     */
    public function setPrivateKey(string $privateKey): void
    {
        $this->privateKey = $privateKey;
    }

    /**
     * 设置账户地址
     */
    public function setAddress(string $address): void
    {
        $this->address = $this->buildAddressArray($address);
    }

    /**
     * 构建地址数组
     *
     * @return array<string, string>
     */
    private function buildAddressArray(string $address): array
    {
        return [
            'hex' => $this->address2HexString($address),
            'base58' => $this->hexString2Address($address),
        ];
    }

    /**
     * 获取账户地址
     *
     * @return array<string, mixed>
     */
    public function getAddress(): array
    {
        return $this->address;
    }

    /**
     * 获取自定义提供者数据
     *
     * @return array<string, mixed>
     */
    public function providers(): array
    {
        return $this->manager->getProviders();
    }

    /**
     * 检查连接的提供者
     *
     * @return array<string, mixed>
     */
    public function isConnected(): array
    {
        return $this->manager->isConnected();
    }

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
     * 返回与筛选条件匹配的所有事件
     *
     * @param mixed $contractAddress
     * @return array<int, array<string, mixed>>
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function getEventResult($contractAddress, int $sinceTimestamp = 0, ?string $eventName = null, int $blockNumber = 0): array
    {
        return $this->blockchainService->getEventResult($contractAddress, $sinceTimestamp, $eventName, $blockNumber);
    }

    /**
     * 返回指定交易 ID 内的所有事件
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function getEventByTransactionID(string $transactionID)
    {
        if (!$this->isValidProvider($this->manager->eventServer())) {
            throw new RuntimeException('No event server configured');
        }

        return $this->manager->request("event/transaction/{$transactionID}");
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
     * 查询账户信息
     *
     * @return array<string, mixed>
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function getAccount(?string $address = null): array
    {
        $address = $this->getAddressOrDefault($address);

        return $this->manager->request('wallet/getaccount', [
            'address' => $address,
        ]);
    }

    /**
     * 获取地址或使用默认地址
     */
    private function getAddressOrDefault(?string $address): string
    {
        if (!is_null($address)) {
            return $this->toHex($address);
        }

        $hexAddress = $this->address['hex'];
        if (!is_string($hexAddress)) {
            throw new RuntimeException('Default hex address is not properly initialized');
        }

        return $hexAddress;
    }

    /**
     * 查询账户信息（返回 VO 对象）
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function getAccountVO(?string $address = null): AccountInfo
    {
        $data = $this->getAccount($address);

        return AccountInfo::fromArray($data);
    }

    /**
     * @return array<string, mixed>
     */
    public function getWalletAccount(string $address): array
    {
        $address = $this->toHex($address);

        return $this->manager->request('wallet/getaccount', [
            'address' => $address,
        ]);
    }

    /**
     * 获取账户余额
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function getBalance(string $address, bool $fromTron = false): float|int
    {
        $account = $this->getAccount($address);
        $balance = $this->extractBalance($account);

        return $fromTron ? $this->fromTron($balance) : $balance;
    }

    /**
     * 从账户数据中提取余额
     *
     * @param array<string, mixed> $account
     */
    private function extractBalance(array $account): int|float
    {
        if (!array_key_exists('balance', $account)) {
            return 0;
        }

        $balance = $account['balance'];
        if (!is_int($balance) && !is_float($balance) && !is_string($balance)) {
            return 0;
        }

        return is_string($balance) ? (int) $balance : $balance;
    }

    /**
     * 获取 Token 余额
     *
     * @return array<string, mixed>|int
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function getTokenBalance(int $tokenId, string $address, bool $fromTron = false): array|int|float
    {
        return $this->blockchainService->getTokenBalance($tokenId, $address, $fromTron);
    }


    /**
     * 查询带宽信息
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function getBandwidth(?string $address = null)
    {
        $address = $this->getAddressOrDefault($address);

        return $this->manager->request('wallet/getaccountnet', [
            'address' => $address,
        ]);
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
     * 从响应中提取数字值
     *
     * @param mixed $response
     */
    private function extractNumericValue($response, string $key): int
    {
        if (!is_array($response) || !isset($response[$key])) {
            return 0;
        }

        $value = $response[$key];
        if (!is_int($value) && !is_float($value) && !is_string($value)) {
            return 0;
        }

        return is_numeric($value) ? (int) $value : 0;
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

    /**
     * 基于 Tron 创建新的 Token
     *
     * @param array token {
     *   "owner_address": "41e552f6487585c2b58bc2c9bb4492bc1f17132cd0",
     *   "name": "0x6173736574497373756531353330383934333132313538",
     *   "abbr": "0x6162627231353330383934333132313538",
     *   "total_supply": 4321,
     *   "trx_num": 1,
     *   "num": 1,
     *   "start_time": 1530894315158,
     *   "end_time": 1533894312158,
     *   "description": "007570646174654e616d6531353330363038383733343633",
     *   "url": "007570646174654e616d6531353330363038383733343633",
     *   "free_asset_net_limit": 10000,
     *   "public_free_asset_net_limit": 10000,
     *   "frozen_supply": { "frozen_amount": 1, "frozen_days": 2 }
     * @param mixed $token
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
     * 创建账户
     * 使用已激活的账户创建新账户
     *
     * @return TransactionInfo|array<string, mixed> 返回 TransactionInfo VO 或原始数组（向后兼容）
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function registerAccount(string $address, string $newAccountAddress): TransactionInfo|array
    {
        return $this->manager->request('wallet/createaccount', [
            'owner_address' => $this->toHex($address),
            'account_address' => $this->toHex($newAccountAddress),
        ]);
    }

    /**
     * 使用已激活的账户创建新账户（返回 VO）
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function registerAccountVO(string $address, string $newAccountAddress): TransactionInfo
    {
        $data = $this->registerAccount($address, $newAccountAddress);
        if ($data instanceof TransactionInfo) {
            return $data;
        }

        return TransactionInfo::fromArray($data);
    }

    /**
     * 申请成为超级代表
     *
     * @return TransactionInfo|array<string, mixed> 返回 TransactionInfo VO 或原始数组（向后兼容）
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function applyForSuperRepresentative(string $address, string $url): TransactionInfo|array
    {
        return $this->manager->request('wallet/createwitness', [
            'owner_address' => $this->toHex($address),
            'url' => $this->stringUtf8toHex($url),
        ]);
    }

    /**
     * 申请成为超级代表（返回 VO）
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function applyForSuperRepresentativeVO(string $address, string $url): TransactionInfo
    {
        $data = $this->applyForSuperRepresentative($address, $url);
        if ($data instanceof TransactionInfo) {
            return $data;
        }

        return TransactionInfo::fromArray($data);
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
     * 冻结一定数量的 TRX
     * 向冻结 Token 的所有者提供带宽或能量以及 TRON Power（投票权）
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function freezeBalance(float $amount = 0, int $duration = 3, string $resource = 'BANDWIDTH', ?string $owner_address = null)
    {
        if (null === $owner_address) {
            $owner_address = $this->address['hex'];
        }

        $freeze = $this->transactionBuilder->freezeBalance($amount, $duration, $resource, $owner_address);
        $signedTransaction = $this->signTransaction($freeze);
        $response = $this->sendRawTransaction($signedTransaction);

        return array_merge($response, $signedTransaction);
    }

    /**
     * 解冻已通过最小冻结期限的 TRX
     * 解冻将移除带宽和 TRON Power
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function unfreezeBalance(string $resource = 'BANDWIDTH', ?string $owner_address = null)
    {
        if (null === $owner_address) {
            $owner_address = $this->address['hex'];
        }

        $unfreeze = $this->transactionBuilder->unfreezeBalance($resource, $owner_address);
        $signedTransaction = $this->signTransaction($unfreeze);
        $response = $this->sendRawTransaction($signedTransaction);

        return array_merge($response, $signedTransaction);
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
     * 节点列表
     *
     * @return array<int, string>
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function listNodes(): array
    {
        $nodes = $this->manager->request('wallet/listnodes');

        if (!is_array($nodes) || !isset($nodes['nodes']) || !is_array($nodes['nodes'])) {
            return [];
        }

        return array_values(array_map(function ($item): string {
            return $this->formatNodeAddress($item);
        }, $nodes['nodes']));
    }

    /**
     * 格式化节点地址
     *
     * @param mixed $item
     */
    private function formatNodeAddress($item): string
    {
        if (!is_array($item) || !isset($item['address']) || !is_array($item['address'])) {
            return '';
        }

        $address = $item['address'];
        $rawHost = $address['host'] ?? '';
        $rawPort = $address['port'] ?? '';

        // 确保类型安全
        $host = is_string($rawHost) ? $rawHost : '';
        $port = is_scalar($rawPort) ? $rawPort : '';

        return sprintf('%s:%s', $this->toUtf8($host), $port);
    }

    /**
     * 节点列表（返回 VO 对象数组）
     *
     * @return array<int, NodeInfo>
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function listNodesVO(): array
    {
        $nodes = $this->manager->request('wallet/listnodes');

        if (!is_array($nodes) || !isset($nodes['nodes']) || !is_array($nodes['nodes'])) {
            return [];
        }

        return $this->extractValidNodeInfos($nodes['nodes']);
    }

    /**
     * 从节点数据中提取有效的NodeInfo对象
     *
     * @param array<mixed> $nodes
     * @return array<int, NodeInfo>
     */
    private function extractValidNodeInfos(array $nodes): array
    {
        $nodeInfos = [];
        foreach ($nodes as $item) {
            if (!is_array($item)) {
                continue;
            }

            // 确保传递关联数组
            assert(array_is_list($item) === false, 'Node data should be associative array');
            /** @var array<string, mixed> $item */
            $nodeInfo = NodeInfo::fromArray($item);
            if ($nodeInfo->isValid()) {
                $nodeInfos[] = $nodeInfo;
            }
        }

        return $nodeInfos;
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

    /**
     * 查询超级代表列表
     *
     * @return array<string, mixed>
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function listSuperRepresentatives(): array
    {
        $response = $this->manager->request('wallet/listwitnesses');

        return $this->extractWitnesses($response);
    }

    /**
     * 从响应中提取见证人列表
     *
     * @param mixed $response
     * @return array<string, mixed>
     * @throws RuntimeException
     */
    private function extractWitnesses($response): array
    {
        if (!is_array($response) || !isset($response['witnesses']) || !is_array($response['witnesses'])) {
            throw new RuntimeException('Invalid response from API');
        }

        /** @var array<string, mixed> $witnesses */
        $witnesses = $response['witnesses'];
        return $witnesses;
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
        $this->validatePaginationParams($limit, $offset);

        if (0 === $limit) {
            return $this->fetchAllTokens();
        }

        return $this->fetchPaginatedTokens($limit, $offset);
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
     * 获取下次超级代表投票的时间
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function timeUntilNextVoteCycle(): float
    {
        $response = $this->manager->request('wallet/getnextmaintenancetime');
        $num = $this->validateMaintenanceTime($response);

        return floor($num / 1000);
    }

    /**
     * 验证维护时间响应
     *
     * @param mixed $response
     * @throws RuntimeException
     */
    private function validateMaintenanceTime($response): int|float
    {
        if (!is_array($response) || !isset($response['num'])) {
            throw new RuntimeException('Invalid response format: missing num field');
        }

        $num = $response['num'];
        if (!is_int($num) && !is_float($num)) {
            throw new RuntimeException('Invalid response format: num is not a number');
        }

        if (-1 === $num) {
            throw new RuntimeException('Failed to get time until next vote cycle');
        }

        return $num;
    }

    /**
     * 验证地址
     *
     * @return AddressValidation|array<string, mixed> 返回 AddressValidation VO 或原始数组（向后兼容）
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function validateAddress(string $address = '', bool $hex = false): AddressValidation|array
    {
        $address = ('' !== $address ? $address : $this->address['hex']);
        if ($hex) {
            assert(is_string($address), 'address must be a string for toHex conversion');
            $address = $this->toHex($address);
        }

        return $this->manager->request('wallet/validateaddress', [
            'address' => $address,
        ]);
    }

    /**
     * 验证地址（返回 VO）
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function validateAddressVO(string $address = '', bool $hex = false): AddressValidation
    {
        $data = $this->validateAddress($address, $hex);
        if ($data instanceof AddressValidation) {
            return $data;
        }

        return AddressValidation::fromArray($data);
    }

    /**
     * 验证 Tron 地址（本地）
     */
    public function isAddress(?string $address = null): bool
    {
        if (!$this->isValidAddressLength($address)) {
            return false;
        }

        // $address is guaranteed to be string here after length validation
        assert(is_string($address), 'Address must be string after validation');
        $addressHex = Base58Check::decode($address, 0, 0, false);

        $utf8 = hex2bin($addressHex);

        if (!is_string($utf8) || !$this->isValidAddressBinary($utf8)) {
            return false;
        }

        return $this->verifyAddressChecksum($utf8);
    }

    /**
     * 验证地址长度是否有效
     */
    private function isValidAddressLength(?string $address): bool
    {
        return null !== $address && self::ADDRESS_SIZE === strlen($address);
    }

    /**
     * 验证地址二进制数据是否有效
     *
     * @param string|false $utf8
     */
    private function isValidAddressBinary($utf8): bool
    {
        if (false === $utf8 || 25 !== strlen($utf8)) {
            return false;
        }

        return 0 === strpos($utf8, chr(self::ADDRESS_PREFIX_BYTE));
    }

    /**
     * 验证地址校验和
     */
    private function verifyAddressChecksum(string $utf8): bool
    {
        $checkSum = substr($utf8, 21);
        $addressBin = substr($utf8, 0, 21);

        $hash0 = Hash::SHA256($addressBin);
        $hash1 = Hash::SHA256($hash0);
        $checkSum1 = substr($hash1, 0, 4);

        return $checkSum === $checkSum1;
    }

    /**
     * 部署一个合约
     *
     * @param string $abi
     * @param string $bytecode
     * @param int $feeLimit
     * @param string $address
     * @param int $callValue
     * @param int $bandwidthLimit
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function deployContract(string $abi, string $bytecode, int $feeLimit, string $address, int $callValue = 0, int $bandwidthLimit = 0): array
    {
        $payable = $this->extractPayableConstructor($abi);
        $this->validateDeployContractParams($feeLimit, $payable, $callValue);

        return $this->manager->request('wallet/deploycontract', [
            'owner_address' => $this->toHex($address),
            'fee_limit' => $feeLimit,
            'call_value' => $callValue,
            'consume_user_resource_percent' => $bandwidthLimit,
            'abi' => $abi,
            'bytecode' => $bytecode,
        ]);
    }

    /**
     * @return array<int, mixed>
     */
    private function extractPayableConstructor(string $abi): array
    {
        $decoded_abi = json_decode($abi, true);
        if (!is_array($decoded_abi)) {
            return [];
        }

        $filtered = array_filter($decoded_abi, function ($v): bool {
            return $this->isPayableConstructor($v);
        });

        /** @var array<int, mixed> $result */
        $result = array_values($filtered);
        return $result;
    }

    /**
     * 检查是否为可支付的构造函数
     *
     * @param mixed $v
     */
    private function isPayableConstructor($v): bool
    {
        if (!is_array($v)) {
            return false;
        }

        return isset($v['type'])
            && 'constructor' === $v['type']
            && isset($v['payable'])
            && $v['payable'];
    }

    /**
     * @param array<int, mixed> $payable
     */
    private function validateDeployContractParams(int $feeLimit, array $payable, int $callValue): void
    {
        $this->validateFeeLimit($feeLimit);
        $this->validateCallValue($payable, $callValue);
    }

    /**
     * 验证费用限制
     */
    private function validateFeeLimit(int $feeLimit): void
    {
        if ($feeLimit > 1000000000) {
            throw new RuntimeException('fee_limit must not be greater than 1000000000');
        }
    }

    /**
     * 验证调用价值
     *
     * @param array<int, mixed> $payable
     */
    private function validateCallValue(array $payable, int $callValue): void
    {
        if ([] !== $payable && 0 === $callValue) {
            throw new RuntimeException('call_value must be greater than 0 if contract is type payable');
        }

        if ([] === $payable && $callValue > 0) {
            throw new RuntimeException("call_value can only equal to 0 if contract type isn't payable");
        }
    }

    /**
     * 创建新账户
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function createAccount(): TronAddress
    {
        return $this->generateAddress();
    }

    public function getAddressHex(string $pubKeyBin): string
    {
        if (65 === strlen($pubKeyBin)) {
            $pubKeyBin = substr($pubKeyBin, 1);
        }

        $hash = Keccak::hash($pubKeyBin, 256);

        return self::ADDRESS_PREFIX . substr($hash, 24);
    }

    public function getBase58CheckAddress(string $addressBin): string
    {
        $hash0 = Hash::SHA256($addressBin);
        $hash1 = Hash::SHA256($hash0);
        $checksum = substr($hash1, 0, 4);
        $checksum = $addressBin . $checksum;

        return Base58::encode(Crypto::bin2bc($checksum));
    }

    /**
     * 生成新地址
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function generateAddress(): TronAddress
    {
        $ec = new EC('secp256k1');
        $key = $ec->genKeyPair();

        $keyPair = $this->extractKeyPair($ec, $key);
        $addressData = $this->buildAddressData($keyPair);

        return new TronAddress($addressData);
    }

    /**
     * 提取密钥对
     *
     * @param EC $ec
     * @param mixed $key
     * @return array{priv: mixed, pubKeyHex: string, privateKeyHex: string}
     */
    private function extractKeyPair(EC $ec, $key): array
    {
        assert(is_object($key) && property_exists($key, 'priv'), 'Key pair must have priv property');
        $priv = $ec->keyFromPrivate($key->priv);

        $pubKeyHex = $priv->getPublic(false, 'hex');
        assert(is_string($pubKeyHex), 'Public key must be a string');

        $privateKeyHex = $priv->getPrivate('hex');
        assert(is_string($privateKeyHex), 'Private key must be a string');

        return [
            'priv' => $priv,
            'pubKeyHex' => $pubKeyHex,
            'privateKeyHex' => $privateKeyHex,
        ];
    }

    /**
     * 构建地址数据
     *
     * @param array{priv: mixed, pubKeyHex: string, privateKeyHex: string} $keyPair
     * @return array{private_key: string, public_key: string, address_hex: string, address_base58: string}
     * @throws RuntimeException
     */
    private function buildAddressData(array $keyPair): array
    {
        $pubKeyBin = hex2bin($keyPair['pubKeyHex']);
        if (false === $pubKeyBin) {
            throw new RuntimeException('Failed to decode public key');
        }

        $addressHex = $this->getAddressHex($pubKeyBin);
        $addressBin = hex2bin($addressHex);
        if (false === $addressBin) {
            throw new RuntimeException('Failed to decode address hex');
        }

        $addressBase58 = $this->getBase58CheckAddress($addressBin);

        return [
            'private_key' => $keyPair['privateKeyHex'],
            'public_key' => $keyPair['pubKeyHex'],
            'address_hex' => $addressHex,
            'address_base58' => $addressBase58,
        ];
    }

    /**
     * 辅助函数，将 HEX 转换为 UTF8
     * @param mixed $str
     */
    public function toUtf8($str): string
    {
        return pack('H*', $str);
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

    /**
     * @return array<string, mixed>
     */
    public function getContract(string $address): array
    {
        return $this->manager->request('/wallet/getcontract', [
            'value' => $address,
            'visible' => true,
        ]);
    }
}
