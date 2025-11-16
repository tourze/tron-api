<?php

namespace Tourze\TronAPI\Tests;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\TronAPI\Exception\RuntimeException;
use Tourze\TronAPI\Provider\HttpProviderInterface;
use Tourze\TronAPI\Support\BigInteger;
use Tourze\TronAPI\TransactionBuilder;
use Tourze\TronAPI\TRC20Contract;
use Tourze\TronAPI\Tron;
use Tourze\TronAPI\TronAddress;
use Tourze\TronAPI\TronManager;
use Tourze\TronAPI\ValueObject\AddressValidation;
use Tourze\TronAPI\ValueObject\TransactionInfo;

/**
 * @internal
 */
#[CoversClass(Tron::class)]
class TronTest extends TestCase
{
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
        $mockProvider = $this->createMock(HttpProviderInterface::class);
        $this->assertTrue($tron->isValidProvider($mockProvider));

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
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->with('wallet/getaccount', Assert::callback(function ($params) {
                return isset($params['address']);
            }))
            ->willReturn([
                'address' => '41e472f387585c2b58bc2c9bb4492bc1f17342cd0',
                'balance' => 1000000,
            ])
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);

        $account = $tron->getAccount('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY');

        $this->assertIsArray($account);
        $this->assertArrayHasKey('address', $account);
        $this->assertArrayHasKey('balance', $account);
    }

    public function testGetBalance(): void
    {
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->with('wallet/getaccount')
            ->willReturn([
                'address' => '41e472f387585c2b58bc2c9bb4492bc1f17342cd0',
                'balance' => 1000000,
            ])
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);

        $balance = $tron->getBalance('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY');

        $this->assertSame(1000000, $balance);
    }

    public function testGetBalanceReturnsZeroWhenNoBalance(): void
    {
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->with('wallet/getaccount')
            ->willReturn([
                'address' => '41e472f387585c2b58bc2c9bb4492bc1f17342cd0',
            ])
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);

        $balance = $tron->getBalance('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY');

        $this->assertSame(0, $balance);
    }

    public function testGetBalanceWithFromTron(): void
    {
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->with('wallet/getaccount')
            ->willReturn([
                'balance' => 1000000,
            ])
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);

        $balance = $tron->getBalance('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY', true);

        // fromTron divides by 1000000
        $this->assertSame(1.0, $balance);
    }

    public function testGetTokenBalance(): void
    {
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->with('wallet/getaccount')
            ->willReturn([
                'address' => '41e472f387585c2b58bc2c9bb4492bc1f17342cd0',
                'assetV2' => [
                    ['key' => 1000001, 'value' => 500000],
                    ['key' => 1000002, 'value' => 300000],
                ],
            ])
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);

        $balance = $tron->getTokenBalance(1000001, 'TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY');

        $this->assertSame(500000, $balance);
    }

    public function testGetTokenBalanceReturnsZeroWhenNoAssets(): void
    {
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->with('wallet/getaccount')
            ->willReturn([
                'address' => '41e472f387585c2b58bc2c9bb4492bc1f17342cd0',
            ])
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);

        $balance = $tron->getTokenBalance(1000001, 'TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY');

        $this->assertSame(0, $balance);
    }

    public function testGetTokenBalanceThrowsExceptionWhenTokenNotFound(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Token id not found');

        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->with('wallet/getaccount')
            ->willReturn([
                'assetV2' => [
                    ['key' => 1000001, 'value' => 500000],
                ],
            ])
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);

        $tron->getTokenBalance(9999999, 'TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY');
    }

    public function testGetCurrentBlock(): void
    {
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->with('wallet/getnowblock')
            ->willReturn([
                'blockID' => '00000000001234567890abcdef',
                'block_header' => ['raw_data' => ['number' => 12345]],
            ])
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);

        $block = $tron->getCurrentBlock();

        $this->assertIsArray($block);
        $this->assertArrayHasKey('blockID', $block);
        $this->assertArrayHasKey('block_header', $block);
    }

    public function testGetBlockByHash(): void
    {
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->with('wallet/getblockbyid', Assert::callback(function ($params) {
                return isset($params['value']);
            }))
            ->willReturn([
                'blockID' => '0000000000abcdef1234567890',
                'block_header' => ['raw_data' => ['number' => 100]],
            ])
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);

        $block = $tron->getBlockByHash('0000000000abcdef1234567890');

        $this->assertIsArray($block);
        $this->assertArrayHasKey('blockID', $block);
    }

    public function testGetBlockByNumber(): void
    {
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->with('wallet/getblockbynum', Assert::callback(function ($params) {
                return isset($params['num']) && 12345 === $params['num'];
            }))
            ->willReturn([
                'blockID' => '0000000000003039',
                'block_header' => ['raw_data' => ['number' => 12345]],
            ])
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);

        $block = $tron->getBlockByNumber(12345);

        $this->assertIsArray($block);
        $this->assertArrayHasKey('blockID', $block);
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

        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->willReturn([])
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);

        $tron->getBlockByNumber(99999999);
    }

    public function testGetBlock(): void
    {
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->with('wallet/getnowblock')
            ->willReturn([
                'blockID' => 'current_block',
                'block_header' => ['raw_data' => ['number' => 99999]],
            ])
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);

        // Test with 'latest' (default)
        $block = $tron->getBlock();

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
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->with('wallet/gettransactionbyid', Assert::callback(function ($params) {
                return isset($params['value']) && 'abc123' === $params['value'];
            }))
            ->willReturn([
                'txID' => 'abc123',
                'raw_data' => ['contract' => []],
            ])
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);

        $tx = $tron->getTransaction('abc123');

        $this->assertIsArray($tx);
        $this->assertArrayHasKey('txID', $tx);
    }

    public function testGetTransactionThrowsExceptionWhenNotFound(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Transaction not found');

        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->willReturn([])
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);

        $tron->getTransaction('nonexistent');
    }

    public function testGetTransactionInfo(): void
    {
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->with('walletsolidity/gettransactioninfobyid', Assert::callback(function ($params) {
                return isset($params['value']) && 'txid123' === $params['value'];
            }))
            ->willReturn([
                'id' => 'txid123',
                'fee' => 100000,
                'blockNumber' => 12345,
            ])
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);

        $info = $tron->getTransactionInfo('txid123');

        $this->assertIsArray($info);
        $this->assertArrayHasKey('id', $info);
        $this->assertArrayHasKey('fee', $info);
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
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->with('wallet/broadcasttransaction', Assert::callback(function ($params) {
                return isset($params['signature']) && isset($params['txID']);
            }))
            ->willReturn([
                'result' => true,
                'txid' => 'broadcasted_tx_id',
            ])
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);

        $signedTx = [
            'txID' => 'abc123',
            'raw_data' => [],
            'signature' => ['sig123'],
        ];

        $result = $tron->sendRawTransaction($signedTx);

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
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->with('wallet/getnowblock')
            ->willReturn([
                'blockID' => 'block123',
                'transactions' => [
                    ['txID' => 'tx1'],
                    ['txID' => 'tx2'],
                    ['txID' => 'tx3'],
                ],
            ])
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);

        $count = $tron->getBlockTransactionCount();

        $this->assertSame(3, $count);
    }

    public function testGetBlockTransactionCountReturnsZeroWhenNoTransactions(): void
    {
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->with('wallet/getnowblock')
            ->willReturn([
                'blockID' => 'block123',
                'transactions' => [],
            ])
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);

        $count = $tron->getBlockTransactionCount();

        $this->assertSame(0, $count);
    }

    public function testGetTransactionFromBlock(): void
    {
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->with('wallet/getnowblock')
            ->willReturn([
                'blockID' => 'block123',
                'transactions' => [
                    ['txID' => 'tx1'],
                    ['txID' => 'tx2'],
                    ['txID' => 'tx3'],
                ],
            ])
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);

        $tx = $tron->getTransactionFromBlock(null, 1);

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

        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->willReturn([
                'blockID' => 'block123',
                'transactions' => [
                    ['txID' => 'tx1'],
                ],
            ])
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);

        $tron->getTransactionFromBlock(null, 5);
    }

    public function testValidateAddress(): void
    {
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->with('wallet/validateaddress', Assert::callback(function ($params) {
                return isset($params['address']);
            }))
            ->willReturn([
                'result' => true,
                'message' => 'Valid address',
            ])
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);

        $result = $tron->validateAddress('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY');

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
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->with('wallet/totaltransaction')
            ->willReturn([
                'num' => 123456,
            ])
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);

        $count = $tron->getTransactionCount();

        $this->assertSame(123456, $count);
    }

    public function testGetTransactionCountReturnsZeroWhenNoData(): void
    {
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->with('wallet/totaltransaction')
            ->willReturn([])
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);

        $count = $tron->getTransactionCount();

        $this->assertSame(0, $count);
    }

    public function testListNodes(): void
    {
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->with('wallet/listnodes')
            ->willReturn([
                'nodes' => [
                    ['address' => ['host' => '7f000001', 'port' => 8080]],
                    ['address' => ['host' => 'c0a80001', 'port' => 8090]],
                ],
            ])
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);

        $nodes = $tron->listNodes();

        $this->assertIsArray($nodes);
        $this->assertCount(2, $nodes);
    }

    public function testListNodesReturnsEmptyArrayWhenNoNodes(): void
    {
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->with('wallet/listnodes')
            ->willReturn([])
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);

        $nodes = $tron->listNodes();

        $this->assertIsArray($nodes);
        $this->assertEmpty($nodes);
    }

    public function testGetBlockRange(): void
    {
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->with('wallet/getblockbylimitnext', Assert::callback(function ($params) {
                return 10 === $params['startNum'] && 21 === $params['endNum'];
            }))
            ->willReturn([
                'block' => [
                    ['blockID' => 'block10'],
                    ['blockID' => 'block11'],
                ],
            ])
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);

        $blocks = $tron->getBlockRange(10, 20);

        $this->assertIsArray($blocks);
        $this->assertCount(2, $blocks);
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
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->with('wallet/getblockbylatestnum', Assert::callback(function ($params) {
                return 5 === $params['num'];
            }))
            ->willReturn([
                'block' => [
                    ['blockID' => 'latest1'],
                    ['blockID' => 'latest2'],
                ],
            ])
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);

        $blocks = $tron->getLatestBlocks(5);

        $this->assertIsArray($blocks);
        $this->assertCount(2, $blocks);
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
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->with('wallet/listwitnesses')
            ->willReturn([
                'witnesses' => [
                    ['address' => 'witness1'],
                    ['address' => 'witness2'],
                ],
            ])
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);

        $witnesses = $tron->listSuperRepresentatives();

        $this->assertIsArray($witnesses);
        $this->assertCount(2, $witnesses);
    }

    public function testListTokens(): void
    {
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->with('wallet/getassetissuelist')
            ->willReturn([
                'assetIssue' => [
                    ['id' => 'token1'],
                    ['id' => 'token2'],
                ],
            ])
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);

        $tokens = $tron->listTokens();

        $this->assertIsArray($tokens);
        $this->assertCount(2, $tokens);
    }

    public function testListTokensWithPagination(): void
    {
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->with('wallet/getpaginatedassetissuelist', Assert::callback(function ($params) {
                return 10 === $params['limit'] && 5 === $params['offset'];
            }))
            ->willReturn([
                'assetIssue' => [
                    ['id' => 'token1'],
                ],
            ])
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);

        $tokens = $tron->listTokens(10, 5);

        $this->assertIsArray($tokens);
        $this->assertCount(1, $tokens);
    }

    public function testTimeUntilNextVoteCycle(): void
    {
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->with('wallet/getnextmaintenancetime')
            ->willReturn([
                'num' => 60000,
            ])
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);

        $time = $tron->timeUntilNextVoteCycle();

        $this->assertSame(60.0, $time);
    }

    public function testTimeUntilNextVoteCycleThrowsExceptionOnFailure(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to get time until next vote cycle');

        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->with('wallet/getnextmaintenancetime')
            ->willReturn([
                'num' => -1,
            ])
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);

        $tron->timeUntilNextVoteCycle();
    }

    public function testGetTransactionsRelated(): void
    {
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->with('walletextension/gettransactionstothis', Assert::callback(function ($params) {
                return isset($params['account']) && 30 === $params['limit'] && 0 === $params['offset'];
            }))
            ->willReturn([
                'transactions' => [
                    ['txID' => 'tx1'],
                    ['txID' => 'tx2'],
                ],
            ])
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);

        $result = $tron->getTransactionsRelated('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY', 'to', 30, 0);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('direction', $result);
        $this->assertSame('to', $result['direction']);
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
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->with('walletextension/gettransactionstothis')
            ->willReturn([
                'transactions' => [],
            ])
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);

        $result = $tron->getTransactionsToAddress('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('direction', $result);
        $this->assertSame('to', $result['direction']);
    }

    public function testGetTransactionsFromAddress(): void
    {
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->with('walletextension/gettransactionsfromthis')
            ->willReturn([
                'transactions' => [],
            ])
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);

        $result = $tron->getTransactionsFromAddress('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('direction', $result);
        $this->assertSame('from', $result['direction']);
    }

    public function testGetBandwidth(): void
    {
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->with('wallet/getaccountnet', Assert::callback(function ($params) {
                return isset($params['address']);
            }))
            ->willReturn([
                'freeNetUsed' => 100,
                'freeNetLimit' => 5000,
            ])
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);

        $bandwidth = $tron->getBandwidth('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY');

        $this->assertIsArray($bandwidth);
        $this->assertArrayHasKey('freeNetUsed', $bandwidth);
        $this->assertArrayHasKey('freeNetLimit', $bandwidth);
    }

    public function testGetTokenByID(): void
    {
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->with('/wallet/getassetissuebyid', Assert::callback(function ($params) {
                return '1000001' === $params['value'];
            }))
            ->willReturn([
                'id' => '1000001',
                'name' => 'TestToken',
            ])
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);

        $token = $tron->getTokenByID('1000001');

        $this->assertIsArray($token);
        $this->assertArrayHasKey('id', $token);
        $this->assertArrayHasKey('name', $token);
    }

    public function testGetContract(): void
    {
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->with('/wallet/getcontract', Assert::callback(function ($params) {
                return 'contract_address' === $params['value'] && true === $params['visible'];
            }))
            ->willReturn([
                'bytecode' => 'contract_bytecode',
                'name' => 'MyContract',
            ])
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);

        $contract = $tron->getContract('contract_address');

        $this->assertIsArray($contract);
        $this->assertArrayHasKey('bytecode', $contract);
        $this->assertArrayHasKey('name', $contract);
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
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('isConnected')
            ->willReturn([
                'fullNode' => true,
                'solidityNode' => true,
                'eventServer' => false,
            ])
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);

        $status = $tron->isConnected();

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
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->atLeastOnce())
            ->method('request')
            ->willReturn([
                'result' => true,
                'txID' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
                'raw_data' => ['contract' => []],
                'txid' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
            ])
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);
        $tron->setAddress('TLPbUv85wRUjCfHsCfy74m8Rd5RLV8');
        $tron->setPrivateKey('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');

        $result = $tron->sendTrx('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY', 1.0);

        $this->assertIsArray($result);
    }

    public function testSendToken(): void
    {
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                [
                    'txID' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
                    'raw_data' => ['contract' => []],
                ],
                [
                    'txID' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
                    'raw_data' => ['contract' => []],
                ]
            );

        $tron = new Tron();
        $tron->setManager($mockManager);
        $tron->setAddress('TLPbUv85wRUjCfHsCfy74m8Rd5RLV8');
        $tron->setPrivateKey('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');

        $result = $tron->sendToken('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY', 100, '1000001');

        $this->assertIsArray($result);
    }

    public function testFreezeBalance(): void
    {
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                // First call: wallet/freezebalance
                [
                    'txID' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
                    'raw_data' => ['contract' => []],
                ],
                // Second call: wallet/broadcasttransaction
                [
                    'result' => true,
                    'txid' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
                ]
            )
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);
        $tron->setAddress('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY');
        $tron->setPrivateKey('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');

        $result = $tron->freezeBalance(1000000, 3, 'BANDWIDTH');

        $this->assertIsArray($result);
    }

    public function testUnfreezeBalance(): void
    {
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                [
                    'txID' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
                    'raw_data' => ['contract' => []],
                ],
                [
                    'txID' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
                    'raw_data' => ['contract' => []],
                ]
            )
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);
        $tron->setAddress('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY');
        $tron->setPrivateKey('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');

        $result = $tron->unfreezeBalance('BANDWIDTH');

        $this->assertIsArray($result);
    }

    public function testValidateAddressVO(): void
    {
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->willReturn([
                'result' => true,
                'message' => 'Valid address',
            ])
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);

        $result = $tron->validateAddressVO('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY');

        $this->assertInstanceOf(AddressValidation::class, $result);
    }

    public function testRegisterAccount(): void
    {
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->willReturn([
                'txID' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
                'raw_data' => ['contract' => []],
            ])
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);
        $tron->setAddress('TLPbUv85wRUjCfHsCfy74m8Rd5RLV8');
        $tron->setPrivateKey('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');

        $result = $tron->registerAccount('TLPbUv85wRUjCfHsCfy74m8Rd5RLV8', 'TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY');

        $this->assertIsArray($result);
    }

    public function testRegisterAccountVO(): void
    {
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->willReturn([
                'txID' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
                'raw_data' => ['contract' => []],
            ])
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);
        $tron->setAddress('TLPbUv85wRUjCfHsCfy74m8Rd5RLV8');
        $tron->setPrivateKey('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');

        $result = $tron->registerAccountVO('TLPbUv85wRUjCfHsCfy74m8Rd5RLV8', 'TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY');

        $this->assertInstanceOf(TransactionInfo::class, $result);
    }

    public function testApplyForSuperRepresentative(): void
    {
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->willReturn([
                'txID' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
                'raw_data' => ['contract' => []],
            ])
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);
        $tron->setAddress('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY');
        $tron->setPrivateKey('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');

        $result = $tron->applyForSuperRepresentative('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY', 'https://example.com');

        $this->assertIsArray($result);
    }

    public function testApplyForSuperRepresentativeVO(): void
    {
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->willReturn([
                'txID' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
                'raw_data' => ['contract' => []],
            ])
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);
        $tron->setAddress('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY');
        $tron->setPrivateKey('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');

        $result = $tron->applyForSuperRepresentativeVO('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY', 'https://example.com');

        $this->assertInstanceOf(TransactionInfo::class, $result);
    }

    public function testCreateToken(): void
    {
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->willReturn([
                'txID' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
                'raw_data' => ['contract' => []],
            ])
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);
        $tron->setAddress('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY');
        $tron->setPrivateKey('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');

        $options = [
            'name' => 'Test Token',
            'abbreviation' => 'TTK',
            'totalSupply' => 1000000,
        ];
        $result = $tron->createToken($options);

        $this->assertIsArray($result);
    }

    public function testUpdateToken(): void
    {
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                // First call: wallet/updateasset
                [
                    'txID' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
                    'raw_data' => ['contract' => []],
                ],
                // Second call: wallet/broadcasttransaction
                [
                    'result' => true,
                    'txid' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
                ]
            )
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);
        $tron->setAddress('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY');
        $tron->setPrivateKey('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');

        $result = $tron->updateToken('https://new-url.com', 'Updated description', 1000, 5000);

        $this->assertIsArray($result);
    }

    public function testSendTransaction(): void
    {
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                // First call: wallet/createtransaction
                [
                    'txID' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
                    'raw_data' => ['contract' => []],
                ],
                // Second call: wallet/broadcasttransaction
                [
                    'result' => true,
                    'txid' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
                ]
            )
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);
        $tron->setAddress('TLPbUv85wRUjCfHsCfy74m8Rd5RLV8');
        $tron->setPrivateKey('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');

        $result = $tron->sendTransaction('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY', 1.0);

        $this->assertIsArray($result);
    }

    public function testSendTransactionVO(): void
    {
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                // First call: wallet/createtransaction
                [
                    'txID' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
                    'raw_data' => ['contract' => []],
                ],
                // Second call: wallet/broadcasttransaction
                [
                    'result' => true,
                    'txid' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
                ]
            )
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);
        $tron->setAddress('TLPbUv85wRUjCfHsCfy74m8Rd5RLV8');
        $tron->setPrivateKey('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');

        $result = $tron->sendTransactionVO('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY', 1.0);

        $this->assertInstanceOf(TransactionInfo::class, $result);
    }

    public function testSendTokenTransaction(): void
    {
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                // First call: wallet/transferasset
                [
                    'txID' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
                    'raw_data' => ['contract' => []],
                ],
                // Second call: wallet/broadcasttransaction
                [
                    'result' => true,
                    'txid' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
                ]
            )
        ;

        $tron = new Tron();
        $tron->setManager($mockManager);
        $tron->setAddress('TLPbUv85wRUjCfHsCfy74m8Rd5RLV8');
        $tron->setPrivateKey('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');

        $result = $tron->sendTokenTransaction('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY', 100, '1000001');

        $this->assertIsArray($result);
    }

    public function testPurchaseToken(): void
    {
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                [
                    'txID' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
                    'raw_data' => ['contract' => []],
                ],
                [
                    'txID' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
                    'raw_data' => ['contract' => []],
                ]
            );

        $tron = new Tron();
        $tron->setManager($mockManager);
        $tron->setAddress('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY');
        $tron->setPrivateKey('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');

        $result = $tron->purchaseToken('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY', '1000001', 100);

        $this->assertIsArray($result);
    }

    public function testSendOneToMany(): void
    {
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                [
                    'txID' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
                    'raw_data' => ['contract' => []],
                ],
                [
                    'txID' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
                    'raw_data' => ['contract' => []],
                ]
            );

        $tron = new Tron();
        $tron->setManager($mockManager);
        $tron->setAddress('TLPbUv85wRUjCfHsCfy74m8Rd5RLV8');
        $tron->setPrivateKey('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');

        $recipients = [
            [0 => 'TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY', 1 => 1000000],
        ];
        $result = $tron->sendOneToMany($recipients);

        $this->assertIsArray($result);
    }

    public function testDeployContract(): void
    {
        $mockManager = $this->createMock(TronManager::class);
        $mockManager->expects($this->once())
            ->method('request')
            ->willReturn([
                'txID' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
                'raw_data' => ['contract' => []],
            ]);

        $tron = new Tron();
        $tron->setManager($mockManager);
        $tron->setAddress('TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY');
        $tron->setPrivateKey('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');

        $result = $tron->deployContract('[]', '6080', 1000000000, 'TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY');

        $this->assertIsArray($result);
    }
}
