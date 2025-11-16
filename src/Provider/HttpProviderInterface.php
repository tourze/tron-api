<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Provider;

interface HttpProviderInterface
{
    /**
     * 设置新的状态页面
     */
    public function setStatusPage(string $page = '/'): void;

    /**
     * 检查连接
     */
    public function isConnected(): bool;

    /**
     * 向服务器发送请求
     *
     * @param string $url
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function request($url, array $payload = [], string $method = 'get'): array;
}
