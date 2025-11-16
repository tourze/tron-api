<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Provider;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\StreamInterface;
use Tourze\TronAPI\Exception\InvalidArgumentException;
use Tourze\TronAPI\Exception\NotFoundException;
use Tourze\TronAPI\Exception\RuntimeException;
use Tourze\TronAPI\Exception\TronException;
use Tourze\TronAPI\Support\Utils;

class HttpProvider implements HttpProviderInterface
{
    /**
     * HTTP 客户端处理器
     *
     * @var ClientInterface
     */
    protected $httpClient;

    /**
     * 服务器或 RPC URL
     *
     * @var string
     */
    protected $host;

    /**
     * 等待时间
     *
     * @var int
     */
    protected $timeout = 30000;

    /**
     * 获取自定义请求头
     *
     * @var array<string, string>
     */
    protected $headers = [];

    /**
     * 获取状态页面
     *
     * @var string
     */
    protected $statusPage = '/';

    /**
     * 创建 HttpProvider 对象
     *
     * @param string|false $user
     * @param string|false $password
     * @param array<string, string> $headers
     * @throws TronException
     */
    public function __construct(
        string $host,
        int $timeout = 30000,
        $user = false,
        $password = false,
        array $headers = [],
        string $statusPage = '/'
    ) {
        if (!Utils::isValidUrl($host)) {
            throw new InvalidArgumentException('Invalid URL provided to HttpProvider');
        }

        if (is_nan($timeout) || $timeout < 0) {
            throw new InvalidArgumentException('Invalid timeout duration provided');
        }

        if (!Utils::isArray($headers)) {
            throw new InvalidArgumentException('Invalid headers array provided');
        }

        $this->host = $host;
        $this->timeout = $timeout;
        $this->statusPage = $statusPage;
        // 确保 headers 是 array<string, string>
        foreach ($headers as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $this->headers[$key] = $value;
            }
        }

        $config = [
            'base_uri' => $host,
            'timeout' => $timeout,
        ];

        if (null !== $user) {
            $config['auth'] = [$user, $password];
        }

        $this->httpClient = new Client($config);
    }

    /**
     * 设置新的状态页面
     */
    public function setStatusPage(string $page = '/'): void
    {
        $this->statusPage = $page;
    }

    /**
     * 检查连接
     *
     * @throws TronException
     */
    public function isConnected(): bool
    {
        $response = $this->request($this->statusPage);

        if (array_key_exists('blockID', $response)) {
            return true;
        }
        if (array_key_exists('status', $response)) {
            return true;
        }

        return false;
    }

    /**
     * 获取主机地址
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * 获取超时时间
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * 向服务器发送请求
     *
     * @param string $url
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     * @throws TronException
     */
    public function request($url, array $payload = [], string $method = 'get'): array
    {
        assert(is_string($url), 'URL must be a string');
        $method = strtoupper($method);

        if (!in_array($method, ['GET', 'POST'], true)) {
            throw new InvalidArgumentException('The method is not defined');
        }

        $body = json_encode($payload);
        if (false === $body) {
            $body = '';
        }

        $options = [
            'headers' => $this->headers,
            'body' => $body,
        ];

        $request = new Request($method, $url, $options['headers'], $options['body']);
        $rawResponse = $this->httpClient->send($request, $options);

        return $this->decodeBody(
            $rawResponse->getBody(),
            $rawResponse->getStatusCode()
        );
    }

    /**
     * 将原始响应转换为数组
     *
     * @return array<string, mixed>
     */
    protected function decodeBody(StreamInterface $stream, int $status): array
    {
        $contents = $stream->getContents();
        $decodedBody = json_decode($contents, true);

        if ('OK' === $contents) {
            $decodedBody = [
                'status' => 1,
            ];
        } elseif (null === $decodedBody or !is_array($decodedBody)) {
            $decodedBody = [];
        }

        if (404 === $status) {
            throw new NotFoundException('Page not found');
        }

        // PHPStan 类型断言：确保返回 array<string, mixed>
        /** @var array<string, mixed> $decodedBody */
        return $decodedBody;
    }
}
