<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Provider;

/**
 * 内存中的 HTTP 提供者实现
 *
 * 用于测试场景，允许预设响应数据而不进行真实网络请求。
 * 这是 HttpProviderInterface 的真实实现，而不是 Mock。
 */
class InMemoryHttpProvider implements HttpProviderInterface
{
    private string $statusPage = '/';

    private bool $connected = true;

    /**
     * 预设的请求响应映射
     *
     * @var array<string, array<string, mixed>>
     */
    private array $responses = [];

    /**
     * 默认响应
     *
     * @var array<string, mixed>
     */
    private array $defaultResponse = [];

    /**
     * 记录的请求历史
     *
     * @var array<int, array{url: string, payload: array<string, mixed>, method: string}>
     */
    private array $requestHistory = [];

    /**
     * 设置新的状态页面
     */
    public function setStatusPage(string $page = '/'): void
    {
        $this->statusPage = $page;
    }

    /**
     * 获取状态页面
     */
    public function getStatusPage(): string
    {
        return $this->statusPage;
    }

    /**
     * 设置连接状态
     */
    public function setConnected(bool $connected): self
    {
        $this->connected = $connected;
        return $this;
    }

    /**
     * 检查连接
     */
    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * 为特定 URL 设置预期响应
     *
     * @param array<string, mixed> $response
     */
    public function setResponse(string $url, array $response): self
    {
        $this->responses[$url] = $response;
        return $this;
    }

    /**
     * 批量设置预期响应
     *
     * @param array<string, array<string, mixed>> $responses
     */
    public function setResponses(array $responses): self
    {
        $this->responses = array_merge($this->responses, $responses);
        return $this;
    }

    /**
     * 设置默认响应（当没有匹配的 URL 时使用）
     *
     * @param array<string, mixed> $response
     */
    public function setDefaultResponse(array $response): self
    {
        $this->defaultResponse = $response;
        return $this;
    }

    /**
     * 清除所有预设响应
     */
    public function clearResponses(): self
    {
        $this->responses = [];
        $this->defaultResponse = [];
        return $this;
    }

    /**
     * 向服务器发送请求
     *
     * @param string $url
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function request($url, array $payload = [], string $method = 'get'): array
    {
        assert(is_string($url), 'URL must be a string');

        // 记录请求历史
        $this->requestHistory[] = [
            'url' => $url,
            'payload' => $payload,
            'method' => strtoupper($method),
        ];

        // 查找匹配的响应
        if (isset($this->responses[$url])) {
            return $this->responses[$url];
        }

        // 尝试部分匹配（支持带查询参数的 URL）
        foreach ($this->responses as $pattern => $response) {
            if (str_starts_with($url, $pattern) || str_contains($url, $pattern)) {
                return $response;
            }
        }

        return $this->defaultResponse;
    }

    /**
     * 获取请求历史
     *
     * @return array<int, array{url: string, payload: array<string, mixed>, method: string}>
     */
    public function getRequestHistory(): array
    {
        return $this->requestHistory;
    }

    /**
     * 获取最后一次请求
     *
     * @return array{url: string, payload: array<string, mixed>, method: string}|null
     */
    public function getLastRequest(): ?array
    {
        if ([] === $this->requestHistory) {
            return null;
        }
        return $this->requestHistory[array_key_last($this->requestHistory)];
    }

    /**
     * 清除请求历史
     */
    public function clearRequestHistory(): self
    {
        $this->requestHistory = [];
        return $this;
    }

    /**
     * 获取特定 URL 的请求次数
     */
    public function getRequestCount(string $url): int
    {
        $count = 0;
        foreach ($this->requestHistory as $request) {
            if ($request['url'] === $url || str_contains($request['url'], $url)) {
                $count++;
            }
        }
        return $count;
    }
}
