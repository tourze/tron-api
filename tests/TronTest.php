<?php

namespace Tourze\TronAPI\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\TronAPI\Exception\RuntimeException;
use Tourze\TronAPI\Provider\HttpProviderInterface;
use Tourze\TronAPI\Provider\InMemoryHttpProvider;
use Tourze\TronAPI\Support\BigInteger;
use Tourze\TronAPI\TransactionBuilder;
use Tourze\TronAPI\TRC20Contract;
use Tourze\TronAPI\Tron;
use Tourze\TronAPI\TronAddress;
use Tourze\TronAPI\TronManager;
use Tourze\TronAPI\ValueObject\AddressValidation;
use Tourze\TronAPI\ValueObject\NodeInfo;
use Tourze\TronAPI\ValueObject\TransactionInfo;

/**
 * @internal
 */
#[CoversClass(Tron::class)]
class TronTest extends TestCase
{
    private InMemoryHttpProvider $fullNodeProvider;

    private InMemoryHttpProvider $solidityNodeProvider;

    private Tron $tron;

    protected function setUp(): void
    {
        $this->fullNodeProvider = new InMemoryHttpProvider();
        $this->solidityNodeProvider = new InMemoryHttpProvider();

        $manager = new TronManager([
            'fullNode' => $this->fullNodeProvider,
            'solidityNode' => $this->solidityNodeProvider,
            'eventServer' => new InMemoryHttpProvider(),
            'explorer' => new InMemoryHttpProvider(),
        ]);

        $this->tron = new Tron();
        // 使用反射设置 manager
        $reflection = new \ReflectionProperty(Tron::class, 'manager');
        $reflection->setValue($this->tron, $manager);
    }

    public function testClassExists(): void
    {
        $instance = new Tron();
        $this->assertInstanceOf(Tron::class, $instance);
    }

    public function testCanBeInstantiated(): void
    {
        $tron = new Tron();
        $this->assertInstanceOf(Tron::class, $tron);
    }

    public function testGetManager(): void
    {
        $tron = new Tron();
        $this->assertInstanceOf(TronManager::class, $tron->getManager());
    }

    public function testGetTransactionBuilder(): void
    {
        $tron = new Tron();
        $this->assertInstanceOf(TransactionBuilder::class, $tron->getTransactionBuilder());
    }

    public function testConstants(): void
    {
        $this->assertSame(34, Tron::ADDRESS_SIZE);
        $this->assertSame('41', Tron::ADDRESS_PREFIX);
        $this->assertSame(0x41, Tron::ADDRESS_PREFIX_BYTE);
    }

    public function testSetAndGetAddress(): void
    {
        $tron = new Tron();
        $testAddress = 'TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY';

        $tron->setAddress($testAddress);
        $address = $tron->getAddress();

        $this->assertIsArray($address);
        $this->assertArrayHasKey('hex', $address);
        $this->assertArrayHasKey('base58', $address);
        $this->assertNotNull($address['hex']);
        $this->assertNotNull($address['base58']);
    }

    public function testSetPrivateKey(): void
    {
        $tron = new Tron();
        $privateKey = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

        $tron->setPrivateKey($privateKey);
        // Private key is protected, can't directly verify, but no exception means success
        $this->assertTrue(true);
    }

    public function testSetDefaultBlock(): void
    {
        $tron = new Tron();

        // Test 'latest'
        $tron->setDefaultBlock('latest');
        $this->assertSame('latest', $tron->getDefaultBlock());

        // Test 'earliest'
        $tron->setDefaultBlock('earliest');
        $this->assertSame('earliest', $tron->getDefaultBlock());

        // Test false
        $tron->setDefaultBlock(false);
        $this->assertSame(false, $tron->getDefaultBlock());

        // Test 0
        $tron->setDefaultBlock(0);
        $this->assertSame(0, $tron->getDefaultBlock());

        // Test positive integer
        $tron->setDefaultBlock(12345);
        $this->assertSame(12345, $tron->getDefaultBlock());

        // Test negative integer (should be abs value)
        $tron->setDefaultBlock(-100);
        $this->assertSame(100, $tron->getDefaultBlock());
    }

    public function testSetDefaultBlockThrowsExceptionForInvalidInput(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid block ID provided');

        $tron = new Tron();
        $tron->setDefaultBlock('invalid');
    }

    public function testGetDefaultBlock(): void
    {
        $tron = new Tron();
        $this->assertSame('latest', $tron->getDefaultBlock());
    }

    public function testSetIsObject(): void
    {
        $tron = new Tron();

        $result = $tron->setIsObject(true);
        $this->assertInstanceOf(Tron::class, $result);

        $result = $tron->setIsObject(false);
        $this->assertInstanceOf(Tron::class, $result);
    }

    public function testProviders(): void
    {
        $tron = new Tron();
        $providers = $tron->providers();

        $this->assertIsArray($providers);
        $this->assertArrayHasKey('fullNode', $providers);
        $this->assertArrayHasKey('solidityNode', $providers);
        $this->assertArrayHasKey('eventServer', $providers);
    }

    public function testIsValidProvider(): void
    {
        $tron = new Tron();

        // Test with actual provider
        $provider = new InMemoryHttpProvider();
        $this->assertTrue($tron->isValidProvider($provider));

        // Test with non-provider
        $this->assertFalse($tron->isValidProvider(new \stdClass()));
        $this->assertFalse($tron->isValidProvider('string'));
        $this->assertFalse($tron->isValidProvider(123));
    }

    public function testIsAddressWithValidAddress(): void
    {
        $tron = new Tron();

        // Valid Tron address
        $validAddress = 'TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY';
        $this->assertTrue($tron->isAddress($validAddress));
    }

    public function testIsAddressWithInvalidAddress(): void
    {
        $tron = new Tron();

        // Test null
        $this->assertFalse($tron->isAddress(null));

        // Test wrong length
        $this->assertFalse($tron->isAddress('TLPbUv85wRUjCfHsCfy74m8Rd5RLV8'));

        // Test invalid base58
        $this->assertFalse($tron->isAddress('0000000000000000000000000000000000'));

        // Test empty string
        $this->assertFalse($tron->isAddress(''));
    }

    public function testGetAccount(): void
    {
        $this->fullNodeProvider->setResponse('wallet/getaccount', [
            'address' => '41e472f387585c2b58bc2c9bb4492bc1f17342cd0',
            'balance' => 1000000,
        ]);

        $account = $this->tron->getAccount('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY');

        $this->assertIsArray($account);
        $this->assertArrayHasKey('address', $account);
        $this->assertArrayHasKey('balance', $account);
    }

    public function testGetBalance(): void
    {
        $this->fullNodeProvider->setResponse('wallet/getaccount', [
            'address' => '41e472f387585c2b58bc2c9bb4492bc1f17342cd0',
            'balance' => 1000000,
        ]);

        $balance = $this->tron->getBalance('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY');

        $this->assertSame(1000000, $balance);
    }

    public function testGetBalanceReturnsZeroWhenNoBalance(): void
    {
        $this->fullNodeProvider->setResponse('wallet/getaccount', [
            'address' => '41e472f387585c2b58bc2c9bb4492bc1f17342cd0',
        ]);

        $balance = $this->tron->getBalance('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY');

        $this->assertSame(0, $balance);
    }

    public function testGetBalanceWithFromTron(): void
    {
        $this->fullNodeProvider->setResponse('wallet/getaccount', [
            'balance' => 1000000,
        ]);

        $balance = $this->tron->getBalance('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY', true);

        // fromTron divides by 1000000
        $this->assertSame(1.0, $balance);
    }

    public function testGetTokenBalance(): void
    {
        $this->fullNodeProvider->setResponse('wallet/getaccount', [
            'address' => '41e472f387585c2b58bc2c9bb4492bc1f17342cd0',
            'assetV2' => [
                ['key' => 1000001, 'value' => 500000],
                ['key' => 1000002, 'value' => 300000],
            ],
        ]);

        $balance = $this->tron->getTokenBalance(1000001, 'TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY');

        $this->assertSame(500000, $balance);
    }

    public function testGetTokenBalanceReturnsZeroWhenNoAssets(): void
    {
        $this->fullNodeProvider->setResponse('wallet/getaccount', [
            'address' => '41e472f387585c2b58bc2c9bb4492bc1f17342cd0',
        ]);

        $balance = $this->tron->getTokenBalance(1000001, 'TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY');

        $this->assertSame(0, $balance);
    }

    public function testGetTokenBalanceThrowsExceptionWhenTokenNotFound(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Token id not found');

        $this->fullNodeProvider->setResponse('wallet/getaccount', [
            'assetV2' => [
                ['key' => 1000001, 'value' => 500000],
            ],
        ]);

        $this->tron->getTokenBalance(9999999, 'TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY');
    }

    public function testGetCurrentBlock(): void
    {
        $this->fullNodeProvider->setResponse('wallet/getnowblock', [
            'blockID' => '00000000001234567890abcdef',
            'block_header' => ['raw_data' => ['number' => 12345]],
        ]);

        $block = $this->tron->getCurrentBlock();

        $this->assertIsArray($block);
        $this->assertArrayHasKey('blockID', $block);
        $this->assertArrayHasKey('block_header', $block);
    }

    public function testGetBlockByHash(): void
    {
        $this->fullNodeProvider->setResponse('wallet/getblockbyid', [
            'blockID' => '0000000000abcdef1234567890',
            'block_header' => ['raw_data' => ['number' => 100]],
        ]);

        $block = $this->tron->getBlockByHash('0000000000abcdef1234567890');

        $this->assertIsArray($block);
        $this->assertArrayHasKey('blockID', $block);
    }

    public function testGetBlockByNumber(): void
    {
        $this->fullNodeProvider->setResponse('wallet/getblockbynum', [
            'blockID' => '0000000000003039',
            'block_header' => ['raw_data' => ['number' => 12345]],
        ]);

        $block = $this->tron->getBlockByNumber(12345);

        $this->assertIsArray($block);
        $this->assertArrayHasKey('blockID', $block);

        // 验证请求参数
        $lastRequest = $this->fullNodeProvider->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertSame(12345, $lastRequest['payload']['num']);
    }

    public function testGetBlockByNumberThrowsExceptionForInvalidNumber(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid block number provided');

        $tron = new Tron();
        $tron->getBlockByNumber(-1);
    }

    public function testGetBlockByNumberThrowsExceptionWhenBlockNotFound(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Block not found');

        $this->fullNodeProvider->setResponse('wallet/getblockbynum', []);

        $this->tron->getBlockByNumber(99999999);
    }

    public function testGetBlock(): void
    {
        $this->fullNodeProvider->setResponse('wallet/getnowblock', [
            'blockID' => 'current_block',
            'block_header' => ['raw_data' => ['number' => 99999]],
        ]);

        // Test with 'latest' (default)
        $block = $this->tron->getBlock();

        $this->assertIsArray($block);
        $this->assertArrayHasKey('blockID', $block);
    }

    public function testGetBlockThrowsExceptionWhenNoBlockIdentifier(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No block identifier provided');

        $tron = new Tron();
        $tron->setDefaultBlock(false);
        $tron->getBlock();
    }

    public function testGetTransaction(): void
    {
        $this->fullNodeProvider->setResponse('wallet/gettransactionbyid', [
            'txID' => 'abc123',
            'raw_data' => ['contract' => []],
        ]);

        $tx = $this->tron->getTransaction('abc123');

        $this->assertIsArray($tx);
        $this->assertArrayHasKey('txID', $tx);

        // 验证请求参数
        $lastRequest = $this->fullNodeProvider->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertSame('abc123', $lastRequest['payload']['value']);
    }

    public function testGetTransactionThrowsExceptionWhenNotFound(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Transaction not found');

        $this->fullNodeProvider->setResponse('wallet/gettransactionbyid', []);

        $this->tron->getTransaction('nonexistent');
    }

    public function testGetTransactionInfo(): void
    {
        // walletsolidity 请求会路由到 solidityNode
        $this->solidityNodeProvider->setResponse('walletsolidity/gettransactioninfobyid', [
            'id' => 'txid123',
            'fee' => 100000,
            'blockNumber' => 12345,
        ]);

        $info = $this->tron->getTransactionInfo('txid123');

        $this->assertIsArray($info);
        $this->assertArrayHasKey('id', $info);
        $this->assertArrayHasKey('fee', $info);

        // 验证请求参数
        $lastRequest = $this->solidityNodeProvider->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertSame('txid123', $lastRequest['payload']['value']);
    }

    public function testSignTransaction(): void
    {
        $tron = new Tron();
        $tron->setPrivateKey('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');

        $transaction = [
            'txID' => 'abc123def456',
            'raw_data' => [
                'contract' => [],
            ],
        ];

        $signed = $tron->signTransaction($transaction);

        $this->assertIsArray($signed);
        $this->assertArrayHasKey('signature', $signed);
        $this->assertIsArray($signed['signature']);
        $this->assertNotEmpty($signed['signature']);
    }

    public function testSignTransactionWithMessage(): void
    {
        $tron = new Tron();
        $tron->setPrivateKey('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');

        $transaction = [
            'txID' => 'abc123def456',
            'raw_data' => [
                'contract' => [],
            ],
        ];

        $signed = $tron->signTransaction($transaction, 'Test message');

        $this->assertIsArray($signed);
        $this->assertArrayHasKey('signature', $signed);
        $this->assertArrayHasKey('raw_data', $signed);
        $this->assertArrayHasKey('data', $signed['raw_data']);
    }

    public function testSignTransactionThrowsExceptionWhenNoPrivateKey(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing private key');

        $tron = new Tron();
        $transaction = [
            'txID' => 'abc123',
            'raw_data' => [],
        ];

        $tron->signTransaction($transaction);
    }

    public function testSignTransactionThrowsExceptionWhenAlreadySigned(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Transaction is already signed');

        $tron = new Tron();
        $tron->setPrivateKey('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');

        $transaction = [
            'txID' => 'abc123',
            'raw_data' => [],
            'signature' => ['existing_signature'],
        ];

        $tron->signTransaction($transaction);
    }

    public function testSignTransactionThrowsExceptionWhenMissingTxID(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid transaction structure: missing txID');

        $tron = new Tron();
        $tron->setPrivateKey('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');

        $transaction = [
            'raw_data' => [],
        ];

        $tron->signTransaction($transaction);
    }

    public function testSendRawTransaction(): void
    {
        $this->fullNodeProvider->setResponse('wallet/broadcasttransaction', [
            'result' => true,
            'txid' => 'broadcasted_tx_id',
        ]);

        $signedTx = [
            'txID' => 'abc123',
            'raw_data' => [],
            'signature' => ['sig123'],
        ];

        $result = $this->tron->sendRawTransaction($signedTx);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('result', $result);
    }

    public function testSendRawTransactionThrowsExceptionWhenNotSigned(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Transaction is not signed');

        $tron = new Tron();

        $unsignedTx = [
            'txID' => 'abc123',
            'raw_data' => [],
        ];

        $tron->sendRawTransaction($unsignedTx);
    }

    public function testGetBlockTransactionCount(): void
    {
        $this->fullNodeProvider->setResponse('wallet/getnowblock', [
            'blockID' => 'block123',
            'transactions' => [
                ['txID' => 'tx1'],
                ['txID' => 'tx2'],
                ['txID' => 'tx3'],
            ],
        ]);

        $count = $this->tron->getBlockTransactionCount();

        $this->assertSame(3, $count);
    }

    public function testGetBlockTransactionCountReturnsZeroWhenNoTransactions(): void
    {
        $this->fullNodeProvider->setResponse('wallet/getnowblock', [
            'blockID' => 'block123',
            'transactions' => [],
        ]);

        $count = $this->tron->getBlockTransactionCount();

        $this->assertSame(0, $count);
    }

    public function testGetTransactionFromBlock(): void
    {
        $this->fullNodeProvider->setResponse('wallet/getnowblock', [
            'blockID' => 'block123',
            'transactions' => [
                ['txID' => 'tx1'],
                ['txID' => 'tx2'],
                ['txID' => 'tx3'],
            ],
        ]);

        $tx = $this->tron->getTransactionFromBlock(null, 1);

        $this->assertIsArray($tx);
        $this->assertSame('tx2', $tx['txID']);
    }

    public function testGetTransactionFromBlockThrowsExceptionForInvalidIndex(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid transaction index provided');

        $tron = new Tron();
        $tron->getTransactionFromBlock(null, -1);
    }

    public function testGetTransactionFromBlockThrowsExceptionWhenIndexOutOfRange(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Transaction not found in block');

        $this->fullNodeProvider->setResponse('wallet/getnowblock', [
            'blockID' => 'block123',
            'transactions' => [
                ['txID' => 'tx1'],
            ],
        ]);

        $this->tron->getTransactionFromBlock(null, 5);
    }

    public function testValidateAddress(): void
    {
        $this->fullNodeProvider->setResponse('wallet/validateaddress', [
            'result' => true,
            'message' => 'Valid address',
        ]);

        $result = $this->tron->validateAddress('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('result', $result);
    }

    public function testGenerateAddress(): void
    {
        $tron = new Tron();
        $address = $tron->generateAddress();

        $this->assertInstanceOf(TronAddress::class, $address);
    }

    public function testCreateAccount(): void
    {
        $tron = new Tron();
        $address = $tron->createAccount();

        $this->assertInstanceOf(TronAddress::class, $address);
    }

    public function testGetTransactionCount(): void
    {
        $this->fullNodeProvider->setResponse('wallet/totaltransaction', [
            'num' => 123456,
        ]);

        $count = $this->tron->getTransactionCount();

        $this->assertSame(123456, $count);
    }

    public function testGetTransactionCountReturnsZeroWhenNoData(): void
    {
        $this->fullNodeProvider->setResponse('wallet/totaltransaction', []);

        $count = $this->tron->getTransactionCount();

        $this->assertSame(0, $count);
    }

    public function testListNodes(): void
    {
        $this->fullNodeProvider->setResponse('wallet/listnodes', [
            'nodes' => [
                ['address' => ['host' => '7f000001', 'port' => 8080]],
                ['address' => ['host' => 'c0a80001', 'port' => 8090]],
            ],
        ]);

        $nodes = $this->tron->listNodes();

        $this->assertIsArray($nodes);
        $this->assertCount(2, $nodes);
    }

    public function testListNodesReturnsEmptyArrayWhenNoNodes(): void
    {
        $this->fullNodeProvider->setResponse('wallet/listnodes', []);

        $nodes = $this->tron->listNodes();

        $this->assertIsArray($nodes);
        $this->assertEmpty($nodes);
    }

    public function testListNodesVO(): void
    {
        $this->fullNodeProvider->setResponse('wallet/listnodes', [
            'nodes' => [
                ['address' => ['host' => '7f000001', 'port' => 8080]],
                ['address' => ['host' => 'c0a80001', 'port' => 8090]],
            ],
        ]);

        $nodes = $this->tron->listNodesVO();

        $this->assertIsArray($nodes);
        $this->assertCount(2, $nodes);
        $this->assertContainsOnlyInstancesOf(NodeInfo::class, $nodes);

        // Verify first node
        $this->assertSame('7f000001', $nodes[0]->getHost());
        $this->assertSame(8080, $nodes[0]->getPort());
        $this->assertTrue($nodes[0]->isValid());

        // Verify second node
        $this->assertSame('c0a80001', $nodes[1]->getHost());
        $this->assertSame(8090, $nodes[1]->getPort());
        $this->assertTrue($nodes[1]->isValid());
    }

    public function testListNodesVOReturnsEmptyArrayWhenNoNodes(): void
    {
        $this->fullNodeProvider->setResponse('wallet/listnodes', []);

        $nodes = $this->tron->listNodesVO();

        $this->assertIsArray($nodes);
        $this->assertEmpty($nodes);
    }

    public function testListNodesVOReturnsEmptyArrayWhenNodesFieldMissing(): void
    {
        $this->fullNodeProvider->setResponse('wallet/listnodes', ['status' => 'ok']);

        $nodes = $this->tron->listNodesVO();

        $this->assertIsArray($nodes);
        $this->assertEmpty($nodes);
    }

    public function testListNodesVOFiltersInvalidNodes(): void
    {
        $this->fullNodeProvider->setResponse('wallet/listnodes', [
            'nodes' => [
                ['address' => ['host' => '7f000001', 'port' => 8080]], // Valid
                ['address' => ['host' => '', 'port' => 8080]], // Invalid: empty host
                ['address' => ['host' => 'c0a80001', 'port' => 0]], // Invalid: port is 0
                ['address' => ['host' => 'c0a80002', 'port' => 9000]], // Valid
                'invalid_node', // Invalid: not an array
            ],
        ]);

        $nodes = $this->tron->listNodesVO();

        // Should only return the 2 valid nodes
        $this->assertIsArray($nodes);
        $this->assertCount(2, $nodes);
        $this->assertContainsOnlyInstancesOf(NodeInfo::class, $nodes);

        // Verify first valid node
        $this->assertSame('7f000001', $nodes[0]->getHost());
        $this->assertSame(8080, $nodes[0]->getPort());

        // Verify second valid node
        $this->assertSame('c0a80002', $nodes[1]->getHost());
        $this->assertSame(9000, $nodes[1]->getPort());
    }

    public function testGetBlockRange(): void
    {
        $this->fullNodeProvider->setResponse('wallet/getblockbylimitnext', [
            'block' => [
                ['blockID' => 'block10'],
                ['blockID' => 'block11'],
            ],
        ]);

        $blocks = $this->tron->getBlockRange(10, 20);

        $this->assertIsArray($blocks);
        $this->assertCount(2, $blocks);

        // 验证请求参数
        $lastRequest = $this->fullNodeProvider->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertSame(10, $lastRequest['payload']['startNum']);
        $this->assertSame(21, $lastRequest['payload']['endNum']);
    }

    public function testGetBlockRangeThrowsExceptionForInvalidStart(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid start of range provided');

        $tron = new Tron();
        $tron->getBlockRange(-1, 20);
    }

    public function testGetBlockRangeThrowsExceptionForInvalidEnd(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid end of range provided');

        $tron = new Tron();
        $tron->getBlockRange(20, 10);
    }

    public function testGetLatestBlocks(): void
    {
        $this->fullNodeProvider->setResponse('wallet/getblockbylatestnum', [
            'block' => [
                ['blockID' => 'latest1'],
                ['blockID' => 'latest2'],
            ],
        ]);

        $blocks = $this->tron->getLatestBlocks(5);

        $this->assertIsArray($blocks);
        $this->assertCount(2, $blocks);

        // 验证请求参数
        $lastRequest = $this->fullNodeProvider->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertSame(5, $lastRequest['payload']['num']);
    }

    public function testGetLatestBlocksThrowsExceptionForInvalidLimit(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid limit provided');

        $tron = new Tron();
        $tron->getLatestBlocks(0);
    }

    public function testListSuperRepresentatives(): void
    {
        $this->fullNodeProvider->setResponse('wallet/listwitnesses', [
            'witnesses' => [
                ['address' => 'witness1'],
                ['address' => 'witness2'],
            ],
        ]);

        $witnesses = $this->tron->listSuperRepresentatives();

        $this->assertIsArray($witnesses);
        $this->assertCount(2, $witnesses);
    }

    public function testListTokens(): void
    {
        $this->fullNodeProvider->setResponse('wallet/getassetissuelist', [
            'assetIssue' => [
                ['id' => 'token1'],
                ['id' => 'token2'],
            ],
        ]);

        $tokens = $this->tron->listTokens();

        $this->assertIsArray($tokens);
        $this->assertCount(2, $tokens);
    }

    public function testListTokensWithPagination(): void
    {
        $this->fullNodeProvider->setResponse('wallet/getpaginatedassetissuelist', [
            'assetIssue' => [
                ['id' => 'token1'],
            ],
        ]);

        $tokens = $this->tron->listTokens(10, 5);

        $this->assertIsArray($tokens);
        $this->assertCount(1, $tokens);

        // 验证请求参数
        $lastRequest = $this->fullNodeProvider->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertSame(10, $lastRequest['payload']['limit']);
        $this->assertSame(5, $lastRequest['payload']['offset']);
    }

    public function testTimeUntilNextVoteCycle(): void
    {
        $this->fullNodeProvider->setResponse('wallet/getnextmaintenancetime', [
            'num' => 60000,
        ]);

        $time = $this->tron->timeUntilNextVoteCycle();

        $this->assertSame(60.0, $time);
    }

    public function testTimeUntilNextVoteCycleThrowsExceptionOnFailure(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to get time until next vote cycle');

        $this->fullNodeProvider->setResponse('wallet/getnextmaintenancetime', [
            'num' => -1,
        ]);

        $this->tron->timeUntilNextVoteCycle();
    }

    public function testGetTransactionsRelated(): void
    {
        // walletextension 请求会路由到 solidityNode
        $this->solidityNodeProvider->setResponse('walletextension/gettransactionstothis', [
            'transactions' => [
                ['txID' => 'tx1'],
                ['txID' => 'tx2'],
            ],
        ]);

        $result = $this->tron->getTransactionsRelated('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY', 'to', 30, 0);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('direction', $result);
        $this->assertSame('to', $result['direction']);

        // 验证请求参数
        $lastRequest = $this->solidityNodeProvider->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertSame(30, $lastRequest['payload']['limit']);
        $this->assertSame(0, $lastRequest['payload']['offset']);
    }

    public function testGetTransactionsRelatedThrowsExceptionForInvalidDirection(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid direction provided: Expected "to", "from"');

        $tron = new Tron();
        $tron->getTransactionsRelated('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY', 'invalid');
    }

    public function testGetTransactionsToAddress(): void
    {
        // walletextension 请求会路由到 solidityNode
        $this->solidityNodeProvider->setResponse('walletextension/gettransactionstothis', [
            'transactions' => [],
        ]);

        $result = $this->tron->getTransactionsToAddress('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('direction', $result);
        $this->assertSame('to', $result['direction']);
    }

    public function testGetTransactionsFromAddress(): void
    {
        // walletextension 请求会路由到 solidityNode
        $this->solidityNodeProvider->setResponse('walletextension/gettransactionsfromthis', [
            'transactions' => [],
        ]);

        $result = $this->tron->getTransactionsFromAddress('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('direction', $result);
        $this->assertSame('from', $result['direction']);
    }

    public function testGetBandwidth(): void
    {
        $this->fullNodeProvider->setResponse('wallet/getaccountnet', [
            'freeNetUsed' => 100,
            'freeNetLimit' => 5000,
        ]);

        $bandwidth = $this->tron->getBandwidth('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY');

        $this->assertIsArray($bandwidth);
        $this->assertArrayHasKey('freeNetUsed', $bandwidth);
        $this->assertArrayHasKey('freeNetLimit', $bandwidth);
    }

    public function testGetTokenByID(): void
    {
        $this->fullNodeProvider->setResponse('/wallet/getassetissuebyid', [
            'id' => '1000001',
            'name' => 'TestToken',
        ]);

        $token = $this->tron->getTokenByID('1000001');

        $this->assertIsArray($token);
        $this->assertArrayHasKey('id', $token);
        $this->assertArrayHasKey('name', $token);

        // 验证请求参数
        $lastRequest = $this->fullNodeProvider->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertSame('1000001', $lastRequest['payload']['value']);
    }

    public function testGetContract(): void
    {
        $this->fullNodeProvider->setResponse('/wallet/getcontract', [
            'bytecode' => 'contract_bytecode',
            'name' => 'MyContract',
        ]);

        $contract = $this->tron->getContract('contract_address');

        $this->assertIsArray($contract);
        $this->assertArrayHasKey('bytecode', $contract);
        $this->assertArrayHasKey('name', $contract);

        // 验证请求参数
        $lastRequest = $this->fullNodeProvider->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertSame('contract_address', $lastRequest['payload']['value']);
        $this->assertTrue($lastRequest['payload']['visible']);
    }

    public function testToUtf8(): void
    {
        $tron = new Tron();

        $hex = '48656c6c6f'; // "Hello" in hex
        $result = $tron->toUtf8($hex);

        $this->assertSame('Hello', $result);
    }

    public function testMakeStaticMethod(): void
    {
        $tron = Tron::make();

        $this->assertInstanceOf(Tron::class, $tron);
    }

    public function testGetFacade(): void
    {
        $tron = new Tron();
        $facade = $tron->getFacade();

        $this->assertSame($tron, $facade);
    }

    public function testContract(): void
    {
        $tron = new Tron();
        $contract = $tron->contract('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY');

        $this->assertInstanceOf(TRC20Contract::class, $contract);
    }

    public function testIsConnected(): void
    {
        // 设置 provider 连接状态
        $this->fullNodeProvider->setConnected(true);

        $status = $this->tron->isConnected();

        $this->assertIsArray($status);
        $this->assertArrayHasKey('fullNode', $status);
    }

    public function testAddress2HexString(): void
    {
        $tron = new Tron();
        $base58Address = 'TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY';

        $hexAddress = $tron->address2HexString($base58Address);

        $this->assertIsString($hexAddress);
        $this->assertStringStartsWith('41', $hexAddress);
        $this->assertSame(42, strlen($hexAddress));
    }

    public function testHexString2Address(): void
    {
        $tron = new Tron();
        $hexAddress = '41e552f6487585c2b58bc2c9bb4492bc1f17132cd0';

        $base58Address = $tron->hexString2Address($hexAddress);

        $this->assertIsString($base58Address);
        $this->assertStringStartsWith('T', $base58Address);
    }

    public function testStringUtf8toHex(): void
    {
        $tron = new Tron();
        $utf8String = 'Hello Tron';

        $hexString = $tron->stringUtf8toHex($utf8String);

        $this->assertIsString($hexString);
        $this->assertSame('48656c6c6f2054726f6e', $hexString);
    }

    public function testHexString2Utf8(): void
    {
        $tron = new Tron();
        $hexString = '48656c6c6f2054726f6e';

        $utf8String = $tron->hexString2Utf8($hexString);

        $this->assertSame('Hello Tron', $utf8String);
    }

    public function testToHex(): void
    {
        $tron = new Tron();

        // Test with regular string
        $result = $tron->toHex('Hello');
        $this->assertSame('48656c6c6f', $result);

        // Test with Tron address
        $address = 'TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY';
        $result = $tron->toHex($address);
        $this->assertStringStartsWith('41', $result);
    }

    public function testFromHex(): void
    {
        $tron = new Tron();

        // Test with hex string
        $hexString = '48656c6c6f';
        $result = $tron->fromHex($hexString);
        $this->assertSame('Hello', $result);

        // Test with hex address
        $hexAddress = '41e552f6487585c2b58bc2c9bb4492bc1f17132cd0';
        $result = $tron->fromHex($hexAddress);
        $this->assertIsString($result);
    }

    public function testToBigNumber(): void
    {
        $tron = new Tron();

        $result = $tron->toBigNumber('1000000');
        $this->assertInstanceOf(BigInteger::class, $result);

        $result = $tron->toBigNumber(1000000);
        $this->assertInstanceOf(BigInteger::class, $result);
    }

    public function testFromTron(): void
    {
        $tron = new Tron();

        // 1 TRX = 1000000 sun
        $result = $tron->fromTron(1000000);
        $this->assertSame(1.0, $result);

        $result = $tron->fromTron(5000000);
        $this->assertSame(5.0, $result);
    }

    public function testToTron(): void
    {
        $tron = new Tron();

        // 1 TRX = 1000000 sun
        $result = $tron->toTron(1.0);
        $this->assertSame(1000000, $result);

        $result = $tron->toTron(5.5);
        $this->assertSame(5500000, $result);
    }

    public function testSha3(): void
    {
        $tron = new Tron();

        $result = $tron->sha3('test');
        $this->assertIsString($result);
        $this->assertStringStartsWith('0x', $result);

        $result = $tron->sha3('test', false);
        $this->assertIsString($result);
        $this->assertStringNotContainsString('0x', $result);
    }

    public function testSendTrx(): void
    {
        $this->fullNodeProvider
            ->setResponse('wallet/createtransaction', [
                'result' => true,
                'txID' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
                'raw_data' => ['contract' => []],
            ])
            ->setResponse('wallet/broadcasttransaction', [
                'result' => true,
                'txid' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
            ]);

        $this->tron->setAddress('TLPbUv85wRUjCfHsCfy74m8Rd5RLV8');
        $this->tron->setPrivateKey('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');

        $result = $this->tron->sendTrx('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY', 1.0);

        $this->assertIsArray($result);
    }

    public function testSendToken(): void
    {
        $this->fullNodeProvider
            ->setResponse('wallet/transferasset', [
                'txID' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
                'raw_data' => ['contract' => []],
            ])
            ->setResponse('wallet/broadcasttransaction', [
                'txID' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
                'raw_data' => ['contract' => []],
            ]);

        $this->tron->setAddress('TLPbUv85wRUjCfHsCfy74m8Rd5RLV8');
        $this->tron->setPrivateKey('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');

        $result = $this->tron->sendToken('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY', 100, '1000001');

        $this->assertIsArray($result);
    }

    public function testFreezeBalance(): void
    {
        $this->fullNodeProvider
            ->setResponse('wallet/freezebalance', [
                'txID' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
                'raw_data' => ['contract' => []],
            ])
            ->setResponse('wallet/broadcasttransaction', [
                'result' => true,
                'txid' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
            ]);

        $this->tron->setAddress('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY');
        $this->tron->setPrivateKey('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');

        $result = $this->tron->freezeBalance(1000000, 3, 'BANDWIDTH');

        $this->assertIsArray($result);
    }

    public function testUnfreezeBalance(): void
    {
        $this->fullNodeProvider
            ->setResponse('wallet/unfreezebalance', [
                'txID' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
                'raw_data' => ['contract' => []],
            ])
            ->setResponse('wallet/broadcasttransaction', [
                'txID' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
                'raw_data' => ['contract' => []],
            ]);

        $this->tron->setAddress('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY');
        $this->tron->setPrivateKey('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');

        $result = $this->tron->unfreezeBalance('BANDWIDTH');

        $this->assertIsArray($result);
    }

    public function testValidateAddressVO(): void
    {
        $this->fullNodeProvider->setResponse('wallet/validateaddress', [
            'result' => true,
            'message' => 'Valid address',
        ]);

        $result = $this->tron->validateAddressVO('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY');

        $this->assertInstanceOf(AddressValidation::class, $result);
    }

    public function testRegisterAccount(): void
    {
        $this->fullNodeProvider->setResponse('wallet/createaccount', [
            'txID' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
            'raw_data' => ['contract' => []],
        ]);

        $this->tron->setAddress('TLPbUv85wRUjCfHsCfy74m8Rd5RLV8');
        $this->tron->setPrivateKey('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');

        $result = $this->tron->registerAccount('TLPbUv85wRUjCfHsCfy74m8Rd5RLV8', 'TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY');

        $this->assertIsArray($result);
    }

    public function testRegisterAccountVO(): void
    {
        $this->fullNodeProvider->setResponse('wallet/createaccount', [
            'txID' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
            'raw_data' => ['contract' => []],
        ]);

        $this->tron->setAddress('TLPbUv85wRUjCfHsCfy74m8Rd5RLV8');
        $this->tron->setPrivateKey('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');

        $result = $this->tron->registerAccountVO('TLPbUv85wRUjCfHsCfy74m8Rd5RLV8', 'TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY');

        $this->assertInstanceOf(TransactionInfo::class, $result);
    }

    public function testApplyForSuperRepresentative(): void
    {
        $this->fullNodeProvider->setResponse('wallet/createwitness', [
            'txID' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
            'raw_data' => ['contract' => []],
        ]);

        $this->tron->setAddress('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY');
        $this->tron->setPrivateKey('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');

        $result = $this->tron->applyForSuperRepresentative('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY', 'https://example.com');

        $this->assertIsArray($result);
    }

    public function testApplyForSuperRepresentativeVO(): void
    {
        $this->fullNodeProvider->setResponse('wallet/createwitness', [
            'txID' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
            'raw_data' => ['contract' => []],
        ]);

        $this->tron->setAddress('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY');
        $this->tron->setPrivateKey('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');

        $result = $this->tron->applyForSuperRepresentativeVO('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY', 'https://example.com');

        $this->assertInstanceOf(TransactionInfo::class, $result);
    }

    public function testCreateToken(): void
    {
        $this->fullNodeProvider->setResponse('wallet/createassetissue', [
            'txID' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
            'raw_data' => ['contract' => []],
        ]);

        $this->tron->setAddress('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY');
        $this->tron->setPrivateKey('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');

        $options = [
            'name' => 'Test Token',
            'abbreviation' => 'TTK',
            'totalSupply' => 1000000,
        ];
        $result = $this->tron->createToken($options);

        $this->assertIsArray($result);
    }

    public function testUpdateToken(): void
    {
        $this->fullNodeProvider
            ->setResponse('wallet/updateasset', [
                'txID' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
                'raw_data' => ['contract' => []],
            ])
            ->setResponse('wallet/broadcasttransaction', [
                'result' => true,
                'txid' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
            ]);

        $this->tron->setAddress('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY');
        $this->tron->setPrivateKey('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');

        $result = $this->tron->updateToken('https://new-url.com', 'Updated description', 1000, 5000);

        $this->assertIsArray($result);
    }

    public function testSendTransaction(): void
    {
        $this->fullNodeProvider
            ->setResponse('wallet/createtransaction', [
                'txID' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
                'raw_data' => ['contract' => []],
            ])
            ->setResponse('wallet/broadcasttransaction', [
                'result' => true,
                'txid' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
            ]);

        $this->tron->setAddress('TLPbUv85wRUjCfHsCfy74m8Rd5RLV8');
        $this->tron->setPrivateKey('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');

        $result = $this->tron->sendTransaction('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY', 1.0);

        $this->assertIsArray($result);
    }

    public function testSendTransactionVO(): void
    {
        $this->fullNodeProvider
            ->setResponse('wallet/createtransaction', [
                'txID' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
                'raw_data' => ['contract' => []],
            ])
            ->setResponse('wallet/broadcasttransaction', [
                'result' => true,
                'txid' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
            ]);

        $this->tron->setAddress('TLPbUv85wRUjCfHsCfy74m8Rd5RLV8');
        $this->tron->setPrivateKey('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');

        $result = $this->tron->sendTransactionVO('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY', 1.0);

        $this->assertInstanceOf(TransactionInfo::class, $result);
    }

    public function testSendTokenTransaction(): void
    {
        $this->fullNodeProvider
            ->setResponse('wallet/transferasset', [
                'txID' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
                'raw_data' => ['contract' => []],
            ])
            ->setResponse('wallet/broadcasttransaction', [
                'result' => true,
                'txid' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
            ]);

        $this->tron->setAddress('TLPbUv85wRUjCfHsCfy74m8Rd5RLV8');
        $this->tron->setPrivateKey('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');

        $result = $this->tron->sendTokenTransaction('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY', 100, '1000001');

        $this->assertIsArray($result);
    }

    public function testPurchaseToken(): void
    {
        $this->fullNodeProvider
            ->setResponse('wallet/participateassetissue', [
                'txID' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
                'raw_data' => ['contract' => []],
            ])
            ->setResponse('wallet/broadcasttransaction', [
                'txID' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
                'raw_data' => ['contract' => []],
            ]);

        $this->tron->setAddress('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY');
        $this->tron->setPrivateKey('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');

        $result = $this->tron->purchaseToken('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY', '1000001', 100);

        $this->assertIsArray($result);
    }

    public function testSendOneToMany(): void
    {
        $this->fullNodeProvider
            ->setResponse('wallet/createtransaction', [
                'txID' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
                'raw_data' => ['contract' => []],
            ])
            ->setResponse('wallet/broadcasttransaction', [
                'txID' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
                'raw_data' => ['contract' => []],
            ]);

        $this->tron->setAddress('TLPbUv85wRUjCfHsCfy74m8Rd5RLV8');
        $this->tron->setPrivateKey('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');

        $recipients = [
            [0 => 'TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY', 1 => 1000000],
        ];
        $result = $this->tron->sendOneToMany($recipients);

        $this->assertIsArray($result);
    }

    public function testDeployContract(): void
    {
        $this->fullNodeProvider->setResponse('wallet/deploycontract', [
            'txID' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
            'raw_data' => ['contract' => []],
        ]);

        $this->tron->setAddress('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY');
        $this->tron->setPrivateKey('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');

        $result = $this->tron->deployContract('[]', '6080', 1000000000, 'TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY');

        $this->assertIsArray($result);
    }
}
