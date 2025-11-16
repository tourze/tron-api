<?php

namespace Tourze\TronAPI\Concerns;

use Tourze\TronAPI\Exception\InvalidArgumentException;

trait ManagesTronscan
{
    /**
     * 从浏览器获取交易记录
     *
     * @param array<string, mixed> $options Tronscan API 查询参数（外部API定义的动态结构）
     *
     * @return array<string, mixed> Tronscan API 响应数据（外部API返回的动态结构）
     *
     * @throws InvalidArgumentException
     */
    public function getTransactionByAddress($options = [])
    {
        if ([] === $options) {
            throw new InvalidArgumentException('Parameters must not be empty.');
        }

        return $this->manager->request('api/transaction', $options);
    }
}
