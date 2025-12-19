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
    use Concerns\ManagesBlocks;
    use Concerns\ManagesTransactions;
    use Concerns\ManagesAccounts;
    use Concerns\ManagesTokens;

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
        ?string $privateKey = null,
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
        ?string $privateKey = null,
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
            assert(false === array_is_list($item), 'Node data should be associative array');
            /** @var array<string, mixed> $item */
            $nodeInfo = NodeInfo::fromArray($item);
            if ($nodeInfo->isValid()) {
                $nodeInfos[] = $nodeInfo;
            }
        }

        return $nodeInfos;
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
