<?php

namespace Tourze\TronAPI\Tests\Provider;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\TronAPI\Exception\InvalidArgumentException;
use Tourze\TronAPI\Exception\NotFoundException;
use Tourze\TronAPI\Provider\HttpProvider;

/**
 * @internal
 */
#[CoversClass(HttpProvider::class)]
class HttpProviderTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $provider = new HttpProvider('https://api.trongrid.io');
        $this->assertInstanceOf(HttpProvider::class, $provider);
    }

    public function testSetStatusPage(): void
    {
        $provider = new HttpProvider('https://api.trongrid.io');
        $provider->setStatusPage('wallet/getnowblock');
        // 验证方法调用成功（无异常抛出即表示成功）
        $this->assertTrue(true);
    }

    public function testRequestWithGetMethod(): void
    {
        // 创建一个 Mock HTTP 响应
        $jsonBody = json_encode([
            'status' => 'success',
            'data' => 'test',
        ]);
        $this->assertIsString($jsonBody, 'JSON encoding failed');

        $mockResponse = new Response(200, [], $jsonBody);

        $provider = $this->createProviderWithMockResponse($mockResponse);

        $result = $provider->request('/test', [], 'get');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
        $this->assertEquals('success', $result['status']);
        $this->assertArrayHasKey('data', $result);
        $this->assertEquals('test', $result['data']);
    }

    public function testRequestWithPostMethod(): void
    {
        $jsonBody = json_encode([
            'result' => 'created',
            'id' => 123,
        ]);
        $this->assertIsString($jsonBody, 'JSON encoding failed');

        $mockResponse = new Response(200, [], $jsonBody);

        $provider = $this->createProviderWithMockResponse($mockResponse);

        $payload = ['name' => 'test', 'value' => 456];
        $result = $provider->request('/create', $payload, 'post');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('result', $result);
        $this->assertEquals('created', $result['result']);
        $this->assertArrayHasKey('id', $result);
        $this->assertEquals(123, $result['id']);
    }

    public function testRequestWithInvalidMethod(): void
    {
        $provider = new HttpProvider('https://api.trongrid.io');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The method is not defined');

        $provider->request('/test', [], 'delete');
    }

    public function testRequestWith404Response(): void
    {
        $mockResponse = new Response(404, [], 'Not Found');

        // 注意：需要配置 http_errors => false，否则 Guzzle 会在 4xx 时抛出 ClientException
        $provider = $this->createProviderWithMockResponse($mockResponse, ['http_errors' => false]);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Page not found');

        $provider->request('/notfound', [], 'get');
    }

    public function testRequestWithOkStringResponse(): void
    {
        // 测试响应体为 "OK" 字符串的情况
        $mockResponse = new Response(200, [], 'OK');

        $provider = $this->createProviderWithMockResponse($mockResponse);

        $result = $provider->request('/status', [], 'get');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
        $this->assertEquals(1, $result['status']);
    }

    public function testRequestWithEmptyJsonResponse(): void
    {
        // 测试响应体为空 JSON 对象的情况
        $mockResponse = new Response(200, [], '{}');

        $provider = $this->createProviderWithMockResponse($mockResponse);

        $result = $provider->request('/empty', [], 'get');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testRequestWithInvalidJsonResponse(): void
    {
        // 测试响应体为无效 JSON 的情况
        $mockResponse = new Response(200, [], 'Invalid JSON {]');

        $provider = $this->createProviderWithMockResponse($mockResponse);

        $result = $provider->request('/invalid', [], 'get');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testRequestWithComplexPayload(): void
    {
        $jsonBody = json_encode([
            'success' => true,
            'received' => 'data',
        ]);
        $this->assertIsString($jsonBody, 'JSON encoding failed');

        $mockResponse = new Response(200, [], $jsonBody);

        $provider = $this->createProviderWithMockResponse($mockResponse);

        $complexPayload = [
            'nested' => [
                'key' => 'value',
                'array' => [1, 2, 3],
            ],
            'boolean' => true,
            'number' => 42,
        ];

        $result = $provider->request('/complex', $complexPayload, 'post');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertTrue($result['success']);
    }

    public function testRequestCaseInsensitiveMethod(): void
    {
        // 测试方法名大小写不敏感
        $jsonBody = json_encode(['result' => 'ok']);
        $this->assertIsString($jsonBody, 'JSON encoding failed');

        $mockResponse = new Response(200, [], $jsonBody);

        $provider = $this->createProviderWithMockResponse($mockResponse);

        $result = $provider->request('/test', [], 'GeT');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('result', $result);
        $this->assertEquals('ok', $result['result']);
    }

    /**
     * 创建一个带 Mock 响应的 HttpProvider
     *
     * @param Response $mockResponse
     * @param array<string, mixed> $clientOptions 额外的 Guzzle Client 选项
     * @return HttpProvider
     */
    private function createProviderWithMockResponse(Response $mockResponse, array $clientOptions = []): HttpProvider
    {
        // 创建 Mock Handler
        $mock = new MockHandler([$mockResponse]);
        $handlerStack = HandlerStack::create($mock);

        // 合并选项
        $options = array_merge(['handler' => $handlerStack], $clientOptions);
        $client = new Client($options);

        // 创建 HttpProvider 实例
        $provider = new HttpProvider('https://api.trongrid.io');

        // 使用反射注入 Mock Client
        $reflection = new \ReflectionClass($provider);
        $property = $reflection->getProperty('httpClient');
        $property->setAccessible(true);
        $property->setValue($provider, $client);

        return $provider;
    }
}
