<?php

declare(strict_types=1);

namespace Tourze\TronAPI\Tests\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\TronAPI\Provider\InMemoryHttpProvider;

/**
 * @internal
 */
#[CoversClass(InMemoryHttpProvider::class)]
class InMemoryHttpProviderTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $provider = new InMemoryHttpProvider();
        $this->assertInstanceOf(InMemoryHttpProvider::class, $provider);
    }

    public function testDefaultStatusPage(): void
    {
        $provider = new InMemoryHttpProvider();
        $this->assertSame('/', $provider->getStatusPage());
    }

    public function testSetStatusPage(): void
    {
        $provider = new InMemoryHttpProvider();
        $provider->setStatusPage('/health');
        $this->assertSame('/health', $provider->getStatusPage());
    }

    public function testIsConnectedDefaultsToTrue(): void
    {
        $provider = new InMemoryHttpProvider();
        $this->assertTrue($provider->isConnected());
    }

    public function testSetConnected(): void
    {
        $provider = new InMemoryHttpProvider();

        $provider->setConnected(false);
        $this->assertFalse($provider->isConnected());

        $provider->setConnected(true);
        $this->assertTrue($provider->isConnected());
    }

    public function testSetConnectedReturnsSelf(): void
    {
        $provider = new InMemoryHttpProvider();
        $result = $provider->setConnected(false);
        $this->assertSame($provider, $result);
    }

    public function testSetResponseAndRequest(): void
    {
        $provider = new InMemoryHttpProvider();

        $response = ['key' => 'value', 'number' => 123];
        $provider->setResponse('test/url', $response);

        $result = $provider->request('test/url', ['param' => 'value']);

        $this->assertSame($response, $result);
    }

    public function testSetResponseReturnsSelf(): void
    {
        $provider = new InMemoryHttpProvider();
        $result = $provider->setResponse('test/url', []);
        $this->assertSame($provider, $result);
    }

    public function testSetResponses(): void
    {
        $provider = new InMemoryHttpProvider();

        $responses = [
            'url1' => ['data' => 'response1'],
            'url2' => ['data' => 'response2'],
        ];
        $provider->setResponses($responses);

        $this->assertSame(['data' => 'response1'], $provider->request('url1'));
        $this->assertSame(['data' => 'response2'], $provider->request('url2'));
    }

    public function testSetDefaultResponse(): void
    {
        $provider = new InMemoryHttpProvider();

        $defaultResponse = ['default' => true];
        $provider->setDefaultResponse($defaultResponse);

        // 未设置响应的 URL 返回默认响应
        $result = $provider->request('unknown/url');

        $this->assertSame($defaultResponse, $result);
    }

    public function testClearResponses(): void
    {
        $provider = new InMemoryHttpProvider();

        $provider->setResponse('test/url', ['data' => 'value']);
        $provider->setDefaultResponse(['default' => true]);

        $provider->clearResponses();

        // 清除后应返回空数组
        $result = $provider->request('test/url');
        $this->assertSame([], $result);
    }

    public function testRequestRecordsHistory(): void
    {
        $provider = new InMemoryHttpProvider();

        $provider->request('url1', ['param1' => 'value1'], 'post');
        $provider->request('url2', ['param2' => 'value2'], 'get');

        $history = $provider->getRequestHistory();

        $this->assertCount(2, $history);
        $this->assertSame('url1', $history[0]['url']);
        $this->assertSame(['param1' => 'value1'], $history[0]['payload']);
        $this->assertSame('POST', $history[0]['method']);
        $this->assertSame('url2', $history[1]['url']);
        $this->assertSame(['param2' => 'value2'], $history[1]['payload']);
        $this->assertSame('GET', $history[1]['method']);
    }

    public function testGetLastRequest(): void
    {
        $provider = new InMemoryHttpProvider();

        $provider->request('first/url', [], 'get');
        $provider->request('last/url', ['key' => 'value'], 'post');

        $lastRequest = $provider->getLastRequest();

        $this->assertNotNull($lastRequest);
        $this->assertSame('last/url', $lastRequest['url']);
        $this->assertSame(['key' => 'value'], $lastRequest['payload']);
        $this->assertSame('POST', $lastRequest['method']);
    }

    public function testGetLastRequestReturnsNullWhenNoHistory(): void
    {
        $provider = new InMemoryHttpProvider();

        $this->assertNull($provider->getLastRequest());
    }

    public function testClearRequestHistory(): void
    {
        $provider = new InMemoryHttpProvider();

        $provider->request('test/url', []);
        $this->assertCount(1, $provider->getRequestHistory());

        $provider->clearRequestHistory();

        $this->assertSame([], $provider->getRequestHistory());
        $this->assertNull($provider->getLastRequest());
    }

    public function testGetRequestCount(): void
    {
        $provider = new InMemoryHttpProvider();

        $provider->request('test/url', []);
        $provider->request('test/url', []);
        $provider->request('other/url', []);

        $this->assertSame(2, $provider->getRequestCount('test/url'));
        $this->assertSame(1, $provider->getRequestCount('other/url'));
        $this->assertSame(0, $provider->getRequestCount('nonexistent'));
    }

    public function testPartialUrlMatching(): void
    {
        $provider = new InMemoryHttpProvider();

        $response = ['matched' => true];
        $provider->setResponse('wallet/getaccount', $response);

        // 带查询参数的 URL 应该匹配
        $result = $provider->request('wallet/getaccount?visible=true');

        $this->assertSame($response, $result);
    }

    public function testRequestMethodConversionToUppercase(): void
    {
        $provider = new InMemoryHttpProvider();

        $provider->request('test/url', [], 'post');
        $provider->request('test/url', [], 'GET');
        $provider->request('test/url', [], 'Put');

        $history = $provider->getRequestHistory();

        $this->assertSame('POST', $history[0]['method']);
        $this->assertSame('GET', $history[1]['method']);
        $this->assertSame('PUT', $history[2]['method']);
    }

    public function testChainedMethodCalls(): void
    {
        $provider = new InMemoryHttpProvider();

        $result = $provider
            ->setConnected(true)
            ->setResponse('url1', ['data' => 1])
            ->setResponse('url2', ['data' => 2])
            ->setDefaultResponse(['default' => true])
            ->clearRequestHistory();

        $this->assertInstanceOf(InMemoryHttpProvider::class, $result);
    }
}
