<?php

namespace Tourze\TronAPI\Concerns;

use Tourze\TronAPI\Exception\ErrorException;

trait ManagesUniversal
{
    /**
     * Default Attributes
     *
     * @var array{balances: array<int, mixed>, one_to_many: array<int, array<string, mixed>|\Tourze\TronAPI\ValueObject\TransactionInfo>}
     */
    private $attribute = [
        'balances' => [],
        'one_to_many' => [],
    ];

    /**
     * 同时向多个地址发送资金
     *
     * @param array<int, array{0: string, 1: float|int}> $to 目标地址和金额的数组，每项为 [地址(string), 金额(float|int)]
     * @param null $private_key
     * @return array<int, array<string, mixed>|\Tourze\TronAPI\ValueObject\TransactionInfo>
     *
     * @throws ErrorException
     */
    public function sendOneToMany(array $to, $private_key = null, bool $isValid = false, ?string $from = null): array
    {
        if (!is_null($private_key)) {
            $this->privateKey = $private_key;
        }

        $this->validateRecipientCount($to);

        foreach ($to as $item) {
            $this->validateTransactionItem($item);

            // validateTransactionItem 已确保 $item[0] 是 string, $item[1] 是 float|int
            assert(is_string($item[0]));
            assert(is_float($item[1]) || is_int($item[1]));

            if ($isValid) {
                $this->validateRecipientAddress($item[0]);
            }

            array_push(
                $this->attribute['one_to_many'],
                $this->send($item[0], (float) $item[1], $from)
            );
        }

        return $this->attribute['one_to_many'];
    }

    /**
     * 验证收款人数量
     *
     * @param array<int, array{0: string, 1: float|int}> $recipients
     * @throws ErrorException
     */
    private function validateRecipientCount(array $recipients): void
    {
        if (count($recipients) > 10) {
            throw new ErrorException('Allowed to send to "10" accounts');
        }
    }

    /**
     * 验证单个交易项的格式
     *
     * @param array<int, string|float|int> $item
     * @throws ErrorException
     */
    private function validateTransactionItem(array $item): void
    {
        if (!is_array($item)) {
            throw new ErrorException('Transaction item must be an array');
        }

        if (!isset($item[0]) || !is_string($item[0])) {
            throw new ErrorException('Invalid address format in transaction item');
        }

        if (!isset($item[1]) || (!is_float($item[1]) && !is_int($item[1]))) {
            throw new ErrorException('Invalid amount format in transaction item');
        }
    }

    /**
     * 验证收款人地址的有效性
     *
     * @throws ErrorException
     */
    private function validateRecipientAddress(string $address): void
    {
        $validationResult = $this->validateAddress($address);

        // validateAddress 可能返回 AddressValidation VO 或数组
        $isInvalid = is_array($validationResult)
            ? ($validationResult['result'] ?? true) === false
            : !$validationResult->isValid();

        if ($isInvalid) {
            throw new ErrorException($address . ' invalid address');
        }
    }
}
