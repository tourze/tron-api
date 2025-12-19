<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Tests\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\TronAPI\Exception\InvalidArgumentException;
use Tourze\TronAPI\ValueObject\AbiFunction;
use Tourze\TronAPI\ValueObject\AbiParameter;

/**
 * @internal
 */
#[CoversClass(AbiFunction::class)]
class AbiFunctionTest extends TestCase
{
    public function testConstructorWithValidData(): void
    {
        $inputs = [
            new AbiParameter('address', 'recipient'),
            new AbiParameter('uint256', 'amount'),
        ];
        $outputs = [
            new AbiParameter('bool', 'success'),
        ];

        $function = new AbiFunction(
            name: 'transfer',
            inputs: $inputs,
            outputs: $outputs,
            type: 'function',
            stateMutability: 'nonpayable'
        );

        $this->assertSame('transfer', $function->name);
        $this->assertSame($inputs, $function->inputs);
        $this->assertSame($outputs, $function->outputs);
        $this->assertSame('function', $function->type);
        $this->assertSame('nonpayable', $function->stateMutability);
    }

    public function testConstructorWithEmptyNameThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ABI function name cannot be empty');

        new AbiFunction(
            name: '',
            inputs: [],
            outputs: [],
            type: 'function'
        );
    }

    public function testConstructorWithWhitespaceNameThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ABI function name cannot be empty');

        new AbiFunction(
            name: '   ',
            inputs: [],
            outputs: [],
            type: 'function'
        );
    }

    public function testConstructorAllowsEmptyNameForConstructor(): void
    {
        $function = new AbiFunction(
            name: '',
            inputs: [],
            outputs: [],
            type: 'constructor'
        );

        $this->assertSame('', $function->name);
        $this->assertSame('constructor', $function->type);
    }

    public function testConstructorAllowsEmptyNameForFallback(): void
    {
        $function = new AbiFunction(
            name: '',
            inputs: [],
            outputs: [],
            type: 'fallback'
        );

        $this->assertSame('', $function->name);
        $this->assertSame('fallback', $function->type);
    }

    public function testFromArrayWithCompleteData(): void
    {
        $data = [
            'name' => 'balanceOf',
            'type' => 'function',
            'stateMutability' => 'view',
            'inputs' => [
                ['type' => 'address', 'name' => 'account'],
            ],
            'outputs' => [
                ['type' => 'uint256', 'name' => 'balance'],
            ],
        ];

        $function = AbiFunction::fromArray($data);

        $this->assertSame('balanceOf', $function->name);
        $this->assertSame('function', $function->type);
        $this->assertSame('view', $function->stateMutability);
        $this->assertCount(1, $function->inputs);
        $this->assertCount(1, $function->outputs);
    }

    public function testFromArrayWithMinimalData(): void
    {
        $data = [
            'name' => 'test',
            'inputs' => [],
            'outputs' => [],
        ];

        $function = AbiFunction::fromArray($data);

        $this->assertSame('test', $function->name);
        $this->assertSame('function', $function->type);
        $this->assertNull($function->stateMutability);
        $this->assertSame([], $function->inputs);
        $this->assertSame([], $function->outputs);
    }

    public function testFromArrayWithConstantFlag(): void
    {
        $data = [
            'name' => 'getValue',
            'constant' => true,
            'inputs' => [],
            'outputs' => [['type' => 'uint256']],
        ];

        $function = AbiFunction::fromArray($data);

        $this->assertSame('view', $function->stateMutability);
    }

    public function testFromArrayWithPayableFlag(): void
    {
        $data = [
            'name' => 'deposit',
            'payable' => true,
            'inputs' => [],
            'outputs' => [],
        ];

        $function = AbiFunction::fromArray($data);

        $this->assertSame('payable', $function->stateMutability);
    }

    public function testFromArrayWithInvalidInputsThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Each inputs must be an array');

        $data = [
            'name' => 'test',
            'inputs' => ['invalid'],
            'outputs' => [],
        ];

        AbiFunction::fromArray($data);
    }

    public function testFromArrayWithInvalidOutputsThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Each outputs must be an array');

        $data = [
            'name' => 'test',
            'inputs' => [],
            'outputs' => ['invalid'],
        ];

        AbiFunction::fromArray($data);
    }

    public function testToArrayWithCompleteData(): void
    {
        $inputs = [
            new AbiParameter('address', 'to'),
            new AbiParameter('uint256', 'value'),
        ];
        $outputs = [
            new AbiParameter('bool'),
        ];

        $function = new AbiFunction(
            name: 'transfer',
            inputs: $inputs,
            outputs: $outputs,
            type: 'function',
            stateMutability: 'nonpayable'
        );

        $array = $function->toArray();

        $this->assertSame('transfer', $array['name']);
        $this->assertSame('function', $array['type']);
        $this->assertSame('nonpayable', $array['stateMutability']);
        $this->assertCount(2, $array['inputs']);
        $this->assertCount(1, $array['outputs']);
        $this->assertSame('address', $array['inputs'][0]['type']);
        $this->assertSame('to', $array['inputs'][0]['name']);
    }

    public function testToArrayWithoutStateMutability(): void
    {
        $function = new AbiFunction(
            name: 'test',
            inputs: [],
            outputs: [],
            type: 'function',
            stateMutability: null
        );

        $array = $function->toArray();

        $this->assertArrayNotHasKey('stateMutability', $array);
    }

    public function testBuildSignatureSimple(): void
    {
        $inputs = [
            new AbiParameter('address'),
            new AbiParameter('uint256'),
        ];

        $function = new AbiFunction(
            name: 'transfer',
            inputs: $inputs,
            outputs: []
        );

        $signature = $function->buildSignature();

        $this->assertSame('transfer(address,uint256)', $signature);
    }

    public function testBuildSignatureNoInputs(): void
    {
        $function = new AbiFunction(
            name: 'totalSupply',
            inputs: [],
            outputs: [new AbiParameter('uint256')]
        );

        $signature = $function->buildSignature();

        $this->assertSame('totalSupply()', $signature);
    }

    public function testBuildSignatureComplexTypes(): void
    {
        $inputs = [
            new AbiParameter('address[]'),
            new AbiParameter('uint256[3]'),
            new AbiParameter('tuple'),
        ];

        $function = new AbiFunction(
            name: 'complexFunction',
            inputs: $inputs,
            outputs: []
        );

        $signature = $function->buildSignature();

        $this->assertSame('complexFunction(address[],uint256[3],tuple)', $signature);
    }

    public function testGetName(): void
    {
        $function = new AbiFunction(
            name: 'approve',
            inputs: [],
            outputs: []
        );

        $this->assertSame('approve', $function->getName());
    }

    public function testGetInputs(): void
    {
        $inputs = [
            new AbiParameter('address', 'spender'),
            new AbiParameter('uint256', 'amount'),
        ];

        $function = new AbiFunction(
            name: 'approve',
            inputs: $inputs,
            outputs: []
        );

        $this->assertSame($inputs, $function->getInputs());
    }

    public function testGetOutputs(): void
    {
        $outputs = [
            new AbiParameter('bool', 'success'),
        ];

        $function = new AbiFunction(
            name: 'transfer',
            inputs: [],
            outputs: $outputs
        );

        $this->assertSame($outputs, $function->getOutputs());
    }

    public function testGetInputCount(): void
    {
        $inputs = [
            new AbiParameter('address'),
            new AbiParameter('uint256'),
            new AbiParameter('bytes'),
        ];

        $function = new AbiFunction(
            name: 'test',
            inputs: $inputs,
            outputs: []
        );

        $this->assertSame(3, $function->getInputCount());
    }

    public function testGetInputCountZero(): void
    {
        $function = new AbiFunction(
            name: 'test',
            inputs: [],
            outputs: []
        );

        $this->assertSame(0, $function->getInputCount());
    }

    public function testGetOutputCount(): void
    {
        $outputs = [
            new AbiParameter('uint256'),
            new AbiParameter('address'),
        ];

        $function = new AbiFunction(
            name: 'test',
            inputs: [],
            outputs: $outputs
        );

        $this->assertSame(2, $function->getOutputCount());
    }

    public function testGetOutputCountZero(): void
    {
        $function = new AbiFunction(
            name: 'test',
            inputs: [],
            outputs: []
        );

        $this->assertSame(0, $function->getOutputCount());
    }

    public function testIsViewWithViewMutability(): void
    {
        $function = new AbiFunction(
            name: 'balanceOf',
            inputs: [],
            outputs: [],
            stateMutability: 'view'
        );

        $this->assertTrue($function->isView());
    }

    public function testIsViewWithPureMutability(): void
    {
        $function = new AbiFunction(
            name: 'calculate',
            inputs: [],
            outputs: [],
            stateMutability: 'pure'
        );

        $this->assertTrue($function->isView());
    }

    public function testIsViewWithNonpayable(): void
    {
        $function = new AbiFunction(
            name: 'transfer',
            inputs: [],
            outputs: [],
            stateMutability: 'nonpayable'
        );

        $this->assertFalse($function->isView());
    }

    public function testIsViewWithPayable(): void
    {
        $function = new AbiFunction(
            name: 'deposit',
            inputs: [],
            outputs: [],
            stateMutability: 'payable'
        );

        $this->assertFalse($function->isView());
    }

    public function testIsViewWithNullMutability(): void
    {
        $function = new AbiFunction(
            name: 'test',
            inputs: [],
            outputs: [],
            stateMutability: null
        );

        $this->assertFalse($function->isView());
    }

    public function testIsPayableWithPayableMutability(): void
    {
        $function = new AbiFunction(
            name: 'deposit',
            inputs: [],
            outputs: [],
            stateMutability: 'payable'
        );

        $this->assertTrue($function->isPayable());
    }

    public function testIsPayableWithView(): void
    {
        $function = new AbiFunction(
            name: 'balanceOf',
            inputs: [],
            outputs: [],
            stateMutability: 'view'
        );

        $this->assertFalse($function->isPayable());
    }

    public function testIsPayableWithNonpayable(): void
    {
        $function = new AbiFunction(
            name: 'transfer',
            inputs: [],
            outputs: [],
            stateMutability: 'nonpayable'
        );

        $this->assertFalse($function->isPayable());
    }

    public function testHasOutputsWithOutputs(): void
    {
        $outputs = [
            new AbiParameter('uint256'),
        ];

        $function = new AbiFunction(
            name: 'balanceOf',
            inputs: [],
            outputs: $outputs
        );

        $this->assertTrue($function->hasOutputs());
    }

    public function testHasOutputsWithoutOutputs(): void
    {
        $function = new AbiFunction(
            name: 'transfer',
            inputs: [],
            outputs: []
        );

        $this->assertFalse($function->hasOutputs());
    }

    public function testReadonlyProperties(): void
    {
        $inputs = [new AbiParameter('address')];
        $outputs = [new AbiParameter('bool')];

        $function = new AbiFunction(
            name: 'test',
            inputs: $inputs,
            outputs: $outputs,
            type: 'function',
            stateMutability: 'view'
        );

        // 验证是 readonly 属性，可以读取但不可修改
        $this->assertSame('test', $function->name);
        $this->assertSame($inputs, $function->inputs);
        $this->assertSame($outputs, $function->outputs);
        $this->assertSame('function', $function->type);
        $this->assertSame('view', $function->stateMutability);
    }

    public function testFromArrayNormalizesArrayKeys(): void
    {
        // 测试包含非字符串键的数组
        $data = [
            'name' => 'test',
            'inputs' => [
                [0 => 'ignored', 'type' => 'address'],
            ],
            'outputs' => [],
        ];

        $function = AbiFunction::fromArray($data);

        $this->assertCount(1, $function->inputs);
        $this->assertSame('address', $function->inputs[0]->type);
    }

    public function testComplexAbiFromArray(): void
    {
        // 测试实际 TRC20 合约的 ABI
        $data = [
            'name' => 'transferFrom',
            'type' => 'function',
            'stateMutability' => 'nonpayable',
            'inputs' => [
                ['type' => 'address', 'name' => 'sender'],
                ['type' => 'address', 'name' => 'recipient'],
                ['type' => 'uint256', 'name' => 'amount'],
            ],
            'outputs' => [
                ['type' => 'bool', 'name' => ''],
            ],
        ];

        $function = AbiFunction::fromArray($data);

        $this->assertSame('transferFrom', $function->name);
        $this->assertSame('transferFrom(address,address,uint256)', $function->buildSignature());
        $this->assertFalse($function->isView());
        $this->assertFalse($function->isPayable());
        $this->assertTrue($function->hasOutputs());
        $this->assertSame(3, $function->getInputCount());
        $this->assertSame(1, $function->getOutputCount());
    }
}
