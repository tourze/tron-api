<?php

namespace Tourze\TronAPI;

use Tourze\TronAPI\Exception\RuntimeException;
use Tourze\TronAPI\Provider\HttpProvider;
use Tourze\TronAPI\Provider\HttpProviderInterface;

class TronManager
{
    /**
     * 默认节点
     *
     * @var array<string, string>
     */
    protected array $defaultNodes = [
        'fullNode' => 'https://api.trongrid.io',
        'solidityNode' => 'https://api.trongrid.io',
        'eventServer' => 'https://api.trongrid.io',
        'explorer' => 'https://apilist.tronscan.org',
        'signServer' => '',
    ];

    /**
     * 提供者列表
     *
     * @var array<string, HttpProviderInterface|null>
     */
    protected array $providers = [
        'fullNode' => null,
        'solidityNode' => null,
        'eventServer' => null,
        'explorer' => null,
        'signServer' => null,
    ];

    /**
     * 状态页面
     *
     * @var array<string, string>
     */
    protected array $statusPage = [
        'fullNode' => 'wallet/getnowblock',
        'solidityNode' => 'walletsolidity/getnowblock',
        'eventServer' => 'healthcheck',
        'explorer' => 'api/system/status',
    ];

    /**
     * @param array<string, HttpProviderInterface|string|null> $providers
     * @throws RuntimeException
     */
    public function __construct(array $providers = [])
    {
        /** @var array<string, HttpProviderInterface|null> $providers */
        $this->providers = $providers;
        $this->initializeProviders();
    }

    private function initializeProviders(): void
    {
        foreach ($this->providers as $key => $value) {
            $this->initializeProvider($key, $value);
            $this->configureProviderStatusPage($key);
        }
    }

    private function initializeProvider(string $key, HttpProviderInterface|string|null $value): void
    {
        // 如果提供者为空，使用默认值
        if (null === $value) {
            if (isset($this->defaultNodes[$key])) {
                $this->providers[$key] = new HttpProvider(
                    $this->defaultNodes[$key]
                );
            }

            return;
        }

        // 如果是字符串，创建 HttpProvider
        if (is_string($value)) {
            $this->providers[$key] = new HttpProvider($value);
        }
    }

    private function configureProviderStatusPage(string $key): void
    {
        if (in_array($key, ['signServer'], true)) {
            return;
        }

        if (isset($this->statusPage[$key], $this->providers[$key])) {
            $this->providers[$key]->setStatusPage($this->statusPage[$key]);
        }
    }

    /**
     * 获取提供者列表
     *
     * @return array<string, HttpProviderInterface|null>
     */
    public function getProviders(): array
    {
        return $this->providers;
    }

    /**
     * 完整节点
     *
     * @throws RuntimeException
     */
    public function fullNode(): HttpProviderInterface
    {
        if (!array_key_exists('fullNode', $this->providers) || null === $this->providers['fullNode']) {
            throw new RuntimeException('Full node is not activated.');
        }

        return $this->providers['fullNode'];
    }

    /**
     * Solidity 节点
     *
     * @throws RuntimeException
     */
    public function solidityNode(): HttpProviderInterface
    {
        if (!array_key_exists('solidityNode', $this->providers) || null === $this->providers['solidityNode']) {
            throw new RuntimeException('Solidity node is not activated.');
        }

        return $this->providers['solidityNode'];
    }

    /**
     * 签名服务器
     *
     * @throws RuntimeException
     */
    public function signServer(): HttpProviderInterface
    {
        if (!array_key_exists('signServer', $this->providers) || null === $this->providers['signServer']) {
            throw new RuntimeException('Sign server is not activated.');
        }

        return $this->providers['signServer'];
    }

    /**
     * TronScan 服务器
     *
     * @throws RuntimeException
     */
    public function explorer(): HttpProviderInterface
    {
        if (!array_key_exists('explorer', $this->providers) || null === $this->providers['explorer']) {
            throw new RuntimeException('explorer is not activated.');
        }

        return $this->providers['explorer'];
    }

    /**
     * 事件服务器
     *
     * @throws RuntimeException
     */
    public function eventServer(): HttpProviderInterface
    {
        if (!array_key_exists('eventServer', $this->providers) || null === $this->providers['eventServer']) {
            throw new RuntimeException('Event server is not activated.');
        }

        return $this->providers['eventServer'];
    }

    /**
     * 向节点发起基础查询
     *
     * @param string $url
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     * @throws RuntimeException
     */
    public function request(string $url, array $params = [], string $method = 'post'): array
    {
        $split = explode('/', $url);
        if (in_array($split[0], ['walletsolidity', 'walletextension'], true)) {
            $response = $this->solidityNode()->request($url, $params, $method);
        } elseif (in_array($split[0], ['event'], true)) {
            $response = $this->eventServer()->request($url, $params, 'get');
        } elseif (in_array($split[0], ['trx-sign'], true)) {
            $response = $this->signServer()->request($url, $params, 'post');
        } elseif (in_array($split[0], ['api'], true)) {
            $response = $this->explorer()->request($url, $params, 'get');
        } else {
            $response = $this->fullNode()->request($url, $params, $method);
        }

        return $response;
    }

    /**
     * 检查连接状态
     *
     * @return array<string, bool>
     */
    public function isConnected(): array
    {
        $array = [];
        foreach ($this->providers as $key => $value) {
            if (null !== $value) {
                $array[$key] = boolval($value->isConnected());
            }
        }

        return $array;
    }
}
