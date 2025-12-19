<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Tests\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tourze\TronAPI\Exception\InvalidArgumentException;
use Tourze\TronAPI\ValueObject\TransactionInfo;

/**
 * @internal
 */
#[CoversClass(TransactionInfo::class)]
class TransactionInfoTest extends TestCase
{
    /**
     * 测试基本的 fromArray 工厂方法和 getter 方法
     */
    public function testFromArrayWithCompleteData(): void
    {
        $data = [
            'txID' => 'abc123',
            'raw_data' => [
                'contract' => [
                    [
                        'type' => 'TransferContract',
                        'parameter' => [
                            'value' => [
                                'amount' => 100,
                                'owner_address' => 'TXYZBase58check',
                                'to_address' => 'TRecipient',
                            ],
                        ],
                    ],
                ],
                'ref_block_bytes' => 'aabb',
                'ref_block_hash' => 'ccdd',
                'expiration' => 1234567890,
                'timestamp' => 1234567800,
            ],
            'signature' => [
                'sig1',
                'sig2',
            ],
            'visible' => true,
            'ret' => [
                [
                    'contractRet' => 'SUCCESS',
                ],
            ],
        ];

        $transactionInfo = TransactionInfo::fromArray($data);

        self::assertSame('abc123', $transactionInfo->getTxID());
        self::assertSame($data['raw_data'], $transactionInfo->getRawData());
        self::assertSame(['sig1', 'sig2'], $transactionInfo->getSignature());
        self::assertTrue($transactionInfo->isVisible());
        self::assertSame($data['ret'], $transactionInfo->getRet());
        self::assertSame($data, $transactionInfo->toArray());
    }

    /**
     * 测试所有字段均为可选的情况
     */
    public function testFromArrayWithMinimalData(): void
    {
        $data = [];

        $transactionInfo = TransactionInfo::fromArray($data);

        self::assertSame('', $transactionInfo->getTxID());
        self::assertSame([], $transactionInfo->getRawData());
        self::assertSame([], $transactionInfo->getSignature());
        self::assertFalse($transactionInfo->isVisible());
        self::assertSame([], $transactionInfo->getRet());
        self::assertSame($data, $transactionInfo->toArray());
    }

    /**
     * 测试 txID 不是字符串时抛出异常
     */
    public function testFromArrayThrowsExceptionForInvalidTxID(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Transaction ID must be a string');

        TransactionInfo::fromArray([
            'txID' => 123, // 非字符串
        ]);
    }

    /**
     * 测试 signature 字段过滤非字符串元素
     */
    public function testFromArrayFiltersInvalidSignatures(): void
    {
        $data = [
            'signature' => [
                'validSig1',
                123, // 非字符串，会被过滤
                'validSig2',
                null, // 非字符串，会被过滤
                'validSig3',
            ],
        ];

        $transactionInfo = TransactionInfo::fromArray($data);

        // 只保留字符串签名，且重建索引
        self::assertSame(['validSig1', 'validSig2', 'validSig3'], $transactionInfo->getSignature());
    }

    /**
     * 测试 visible 字段的类型转换
     */
    #[DataProvider('visibleDataProvider')]
    public function testVisibleConversion(mixed $input, bool $expected): void
    {
        $data = ['visible' => $input];

        $transactionInfo = TransactionInfo::fromArray($data);

        self::assertSame($expected, $transactionInfo->isVisible());
    }

    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function visibleDataProvider(): array
    {
        return [
            'true boolean' => [true, true],
            'false boolean' => [false, false],
            'truthy integer' => [1, true],
            'falsy integer' => [0, false],
            'truthy string' => ['yes', true],
            'empty string' => ['', false],
            'null' => [null, false],
        ];
    }

    /**
     * 测试 isSuccess 方法：成功场景
     */
    public function testIsSuccessReturnsTrueWhenContractRetIsSuccess(): void
    {
        $data = [
            'ret' => [
                [
                    'contractRet' => 'SUCCESS',
                ],
            ],
        ];

        $transactionInfo = TransactionInfo::fromArray($data);

        self::assertTrue($transactionInfo->isSuccess());
    }

    /**
     * 测试 isSuccess 方法：ret 为空数组
     */
    public function testIsSuccessReturnsFalseWhenRetIsEmpty(): void
    {
        $data = ['ret' => []];

        $transactionInfo = TransactionInfo::fromArray($data);

        self::assertFalse($transactionInfo->isSuccess());
    }

    /**
     * 测试 isSuccess 方法：ret 中没有 SUCCESS
     */
    public function testIsSuccessReturnsFalseWhenNoSuccessInRet(): void
    {
        $data = [
            'ret' => [
                [
                    'contractRet' => 'FAILED',
                ],
            ],
        ];

        $transactionInfo = TransactionInfo::fromArray($data);

        self::assertFalse($transactionInfo->isSuccess());
    }

    /**
     * 测试 isSuccess 方法：ret 数组中包含非数组元素
     */
    public function testIsSuccessHandlesNonArrayElementsInRet(): void
    {
        $data = [
            'ret' => [
                'not an array', // 非数组，应跳过
                [
                    'contractRet' => 'SUCCESS',
                ],
            ],
        ];

        $transactionInfo = TransactionInfo::fromArray($data);

        self::assertTrue($transactionInfo->isSuccess());
    }

    /**
     * 测试 isSuccess 方法：ret 中存在 contractRet 但值不是 SUCCESS
     */
    public function testIsSuccessReturnsFalseWhenContractRetIsNotSuccess(): void
    {
        $data = [
            'ret' => [
                [
                    'contractRet' => 'OUT_OF_ENERGY',
                ],
            ],
        ];

        $transactionInfo = TransactionInfo::fromArray($data);

        self::assertFalse($transactionInfo->isSuccess());
    }

    /**
     * 测试 getContractAddress：正常提取合约地址
     */
    public function testGetContractAddressReturnsAddress(): void
    {
        $data = [
            'raw_data' => [
                'contract' => [
                    [
                        'parameter' => [
                            'value' => [
                                'contract_address' => 'TContractAddress123',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $transactionInfo = TransactionInfo::fromArray($data);

        self::assertSame('TContractAddress123', $transactionInfo->getContractAddress());
    }

    /**
     * 测试 getContractAddress：contract 字段不存在
     */
    public function testGetContractAddressReturnsNullWhenContractMissing(): void
    {
        $data = ['raw_data' => []];

        $transactionInfo = TransactionInfo::fromArray($data);

        self::assertNull($transactionInfo->getContractAddress());
    }

    /**
     * 测试 getContractAddress：contract 不是数组
     */
    public function testGetContractAddressReturnsNullWhenContractNotArray(): void
    {
        $data = [
            'raw_data' => [
                'contract' => 'not an array',
            ],
        ];

        $transactionInfo = TransactionInfo::fromArray($data);

        self::assertNull($transactionInfo->getContractAddress());
    }

    /**
     * 测试 getContractAddress：contract 是空数组
     */
    public function testGetContractAddressReturnsNullWhenContractEmpty(): void
    {
        $data = [
            'raw_data' => [
                'contract' => [],
            ],
        ];

        $transactionInfo = TransactionInfo::fromArray($data);

        self::assertNull($transactionInfo->getContractAddress());
    }

    /**
     * 测试 getContractAddress：parameter 字段缺失
     */
    public function testGetContractAddressReturnsNullWhenParameterMissing(): void
    {
        $data = [
            'raw_data' => [
                'contract' => [
                    [
                        'type' => 'TransferContract',
                    ],
                ],
            ],
        ];

        $transactionInfo = TransactionInfo::fromArray($data);

        self::assertNull($transactionInfo->getContractAddress());
    }

    /**
     * 测试 getContractAddress：parameter.value 字段缺失
     */
    public function testGetContractAddressReturnsNullWhenValueMissing(): void
    {
        $data = [
            'raw_data' => [
                'contract' => [
                    [
                        'parameter' => [],
                    ],
                ],
            ],
        ];

        $transactionInfo = TransactionInfo::fromArray($data);

        self::assertNull($transactionInfo->getContractAddress());
    }

    /**
     * 测试 getContractAddress：contract_address 不是字符串
     */
    public function testGetContractAddressReturnsNullWhenAddressNotString(): void
    {
        $data = [
            'raw_data' => [
                'contract' => [
                    [
                        'parameter' => [
                            'value' => [
                                'contract_address' => 123, // 非字符串
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $transactionInfo = TransactionInfo::fromArray($data);

        self::assertNull($transactionInfo->getContractAddress());
    }

    /**
     * 测试 getContractAddress：多个 contract，第一个有效
     */
    public function testGetContractAddressReturnsFirstValidAddress(): void
    {
        $data = [
            'raw_data' => [
                'contract' => [
                    [
                        'parameter' => [
                            'value' => [
                                'contract_address' => 'FirstContract',
                            ],
                        ],
                    ],
                    [
                        'parameter' => [
                            'value' => [
                                'contract_address' => 'SecondContract',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $transactionInfo = TransactionInfo::fromArray($data);

        // 返回第一个有效的合约地址
        self::assertSame('FirstContract', $transactionInfo->getContractAddress());
    }

    /**
     * 测试 getContractAddress：多个 contract，第一个无效，第二个有效
     */
    public function testGetContractAddressSkipsInvalidContracts(): void
    {
        $data = [
            'raw_data' => [
                'contract' => [
                    'not an array', // 无效
                    [
                        'parameter' => [
                            'value' => [
                                'contract_address' => 'ValidContract',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $transactionInfo = TransactionInfo::fromArray($data);

        self::assertSame('ValidContract', $transactionInfo->getContractAddress());
    }

    /**
     * 测试 getType：正常提取交易类型
     */
    public function testGetTypeReturnsType(): void
    {
        $data = [
            'raw_data' => [
                'contract' => [
                    [
                        'type' => 'TransferContract',
                    ],
                ],
            ],
        ];

        $transactionInfo = TransactionInfo::fromArray($data);

        self::assertSame('TransferContract', $transactionInfo->getType());
    }

    /**
     * 测试 getType：contract 字段缺失
     */
    public function testGetTypeReturnsNullWhenContractMissing(): void
    {
        $data = ['raw_data' => []];

        $transactionInfo = TransactionInfo::fromArray($data);

        self::assertNull($transactionInfo->getType());
    }

    /**
     * 测试 getType：contract 不是数组
     */
    public function testGetTypeReturnsNullWhenContractNotArray(): void
    {
        $data = [
            'raw_data' => [
                'contract' => 'not an array',
            ],
        ];

        $transactionInfo = TransactionInfo::fromArray($data);

        self::assertNull($transactionInfo->getType());
    }

    /**
     * 测试 getType：type 字段不是字符串
     */
    public function testGetTypeReturnsNullWhenTypeNotString(): void
    {
        $data = [
            'raw_data' => [
                'contract' => [
                    [
                        'type' => 123, // 非字符串
                    ],
                ],
            ],
        ];

        $transactionInfo = TransactionInfo::fromArray($data);

        self::assertNull($transactionInfo->getType());
    }

    /**
     * 测试 getType：多个 contract，返回第一个有效的 type
     */
    public function testGetTypeReturnsFirstValidType(): void
    {
        $data = [
            'raw_data' => [
                'contract' => [
                    'not an array', // 跳过
                    [
                        'type' => 'TriggerSmartContract',
                    ],
                    [
                        'type' => 'TransferContract',
                    ],
                ],
            ],
        ];

        $transactionInfo = TransactionInfo::fromArray($data);

        self::assertSame('TriggerSmartContract', $transactionInfo->getType());
    }

    /**
     * 测试 getRawField：获取存在的字段
     */
    public function testGetRawFieldReturnsExistingField(): void
    {
        $data = [
            'txID' => 'abc123',
            'customField' => 'customValue',
        ];

        $transactionInfo = TransactionInfo::fromArray($data);

        self::assertSame('customValue', $transactionInfo->getRawField('customField'));
        self::assertSame('abc123', $transactionInfo->getRawField('txID'));
    }

    /**
     * 测试 getRawField：获取不存在的字段
     */
    public function testGetRawFieldReturnsNullForMissingField(): void
    {
        $data = ['txID' => 'abc123'];

        $transactionInfo = TransactionInfo::fromArray($data);

        self::assertNull($transactionInfo->getRawField('nonExistent'));
    }

    /**
     * 测试 toArray 返回原始完整数据
     */
    public function testToArrayReturnsFullData(): void
    {
        $data = [
            'txID' => 'abc123',
            'raw_data' => ['foo' => 'bar'],
            'signature' => ['sig1'],
            'visible' => true,
            'ret' => [['contractRet' => 'SUCCESS']],
            'extraField' => 'extraValue',
        ];

        $transactionInfo = TransactionInfo::fromArray($data);

        self::assertSame($data, $transactionInfo->toArray());
    }

    /**
     * 测试 raw_data 不是数组时使用默认空数组
     */
    public function testRawDataDefaultsToEmptyArrayWhenNotArray(): void
    {
        $data = [
            'raw_data' => 'not an array',
        ];

        $transactionInfo = TransactionInfo::fromArray($data);

        self::assertSame([], $transactionInfo->getRawData());
    }

    /**
     * 测试 signature 不是数组时使用默认空数组
     */
    public function testSignatureDefaultsToEmptyArrayWhenNotArray(): void
    {
        $data = [
            'signature' => 'not an array',
        ];

        $transactionInfo = TransactionInfo::fromArray($data);

        self::assertSame([], $transactionInfo->getSignature());
    }

    /**
     * 测试 ret 不是数组时使用默认空数组
     */
    public function testRetDefaultsToEmptyArrayWhenNotArray(): void
    {
        $data = [
            'ret' => 'not an array',
        ];

        $transactionInfo = TransactionInfo::fromArray($data);

        self::assertSame([], $transactionInfo->getRet());
    }

    /**
     * 测试 isSuccess 当 ret 包含多个结果，只要有一个 SUCCESS 就返回 true
     */
    public function testIsSuccessReturnsTrueWhenAnyResultIsSuccess(): void
    {
        $data = [
            'ret' => [
                [
                    'contractRet' => 'FAILED',
                ],
                [
                    'contractRet' => 'SUCCESS',
                ],
                [
                    'contractRet' => 'OUT_OF_TIME',
                ],
            ],
        ];

        $transactionInfo = TransactionInfo::fromArray($data);

        self::assertTrue($transactionInfo->isSuccess());
    }

    /**
     * 测试 isSuccess 当 ret 包含没有 contractRet 的元素
     */
    public function testIsSuccessReturnsFalseWhenContractRetKeyMissing(): void
    {
        $data = [
            'ret' => [
                [
                    'someOtherKey' => 'someValue',
                ],
            ],
        ];

        $transactionInfo = TransactionInfo::fromArray($data);

        self::assertFalse($transactionInfo->isSuccess());
    }
}
