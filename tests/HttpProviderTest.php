<?php

namespace Tourze\TronAPI\Tests;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use ReflectionClass;
use Tourze\TronAPI\Exception\InvalidArgumentException;
use Tourze\TronAPI\Exception\NotFoundException;
use Tourze\TronAPI\Provider\HttpProvider;

/**
 * @internal
 */
#[CoversClass(HttpProvider::class)]
class HttpProviderTest extends TestCase
{
    private function createProviderWithMockClient(ClientInterface $mockClient): HttpProvider
    {
        $provider = new HttpProvider('https://api.trongrid.io');

        // 通过反射注入 mock client
        $reflection = new \ReflectionClass($provider);
        $clientProp = $reflection->getProperty('httpClient');
        $clientProp->setAccessible(true);
        $clientProp->setValue($provider, $mockClient);

        return $provider;
    }

    public function testCanBeInstantiated(): void
    {
        $provider = new HttpProvider('https://api.trongrid.io');
        $this->assertInstanceOf(HttpProvider::class, $provider);
    }

    public function testConstructorThrowsExceptionForNegativeTimeout(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid timeout duration provided');
        new HttpProvider('https://api.trongrid.io', -1);
    }

    public function testGetHost(): void
    {
        $provider = new HttpProvider('https://api.trongrid.io');
        $this->assertSame('https://api.trongrid.io', $provider->getHost());
    }

    public function testGetTimeout(): void
    {
        $provider = new HttpProvider('https://api.trongrid.io', 5000);
        $this->assertSame(5000, $provider->getTimeout());
    }

    public function testSetStatusPage(): void
    {
        $provider = new HttpProvider('https://api.trongrid.io');
        $provider->setStatusPage('/health');

        $reflection = new \ReflectionClass($provider);
        $prop = $reflection->getProperty('statusPage');
        $prop->setAccessible(true);
        $this->assertSame('/health', $prop->getValue($provider));
    }

    public function testRequestWithGetMethod(): void
    {
        // 模拟响应体
        $mockStream = $this->createMock(StreamInterface::class);
        $mockStream->method('getContents')
            ->willReturn('{"status":"ok"}')
        ;

        // 模拟 HTTP 响应
        $mockResponse = $this->createMock(Response::class);
        $mockResponse->method('getBody')->willReturn($mockStream);
        $mockResponse->method('getStatusCode')->willReturn(200);

        // 模拟 HTTP 客户端
        $mockClient = $this->createMock(ClientInterface::class);
        $mockClient->expects($this->once())
            ->method('send')
            ->willReturn($mockResponse)
        ;

        $provider = $this->createProviderWithMockClient($mockClient);
        $result = $provider->request('/wallet/test');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
        $this->assertSame('ok', $result['status']);
    }

    public function testRequestWithPostMethod(): void
    {
        // 模拟响应体
        $mockStream = $this->createMock(StreamInterface::class);
        $mockStream->method('getContents')
            ->willReturn('{"result":"success"}')
        ;

        // 模拟 HTTP 响应
        $mockResponse = $this->createMock(Response::class);
        $mockResponse->method('getBody')->willReturn($mockStream);
        $mockResponse->method('getStatusCode')->willReturn(200);

        // 模拟 HTTP 客户端
        $mockClient = $this->createMock(ClientInterface::class);
        $mockClient->expects($this->once())
            ->method('send')
            ->willReturn($mockResponse)
        ;

        $provider = $this->createProviderWithMockClient($mockClient);
        $result = $provider->request('/wallet/test', ['key' => 'value'], 'post');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('result', $result);
        $this->assertSame('success', $result['result']);
    }

    public function testRequestWithOKResponse(): void
    {
        // 模拟响应体返回简单的 "OK" 字符串
        $mockStream = $this->createMock(StreamInterface::class);
        $mockStream->method('getContents')->willReturn('OK');

        $mockResponse = $this->createMock(Response::class);
        $mockResponse->method('getBody')->willReturn($mockStream);
        $mockResponse->method('getStatusCode')->willReturn(200);

        $mockClient = $this->createMock(ClientInterface::class);
        $mockClient->method('send')->willReturn($mockResponse);

        $provider = $this->createProviderWithMockClient($mockClient);
        $result = $provider->request('/test');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
        $this->assertSame(1, $result['status']);
    }

    public function testRequestThrowsExceptionForInvalidMethod(): void
    {
        $provider = new HttpProvider('https://api.trongrid.io');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The method is not defined');
        $provider->request('/test', [], 'DELETE');
    }

    public function testRequestThrows404Exception(): void
    {
        // 模拟 404 响应
        $mockStream = $this->createMock(StreamInterface::class);
        $mockStream->method('getContents')->willReturn('{"error":"not found"}');

        $mockResponse = $this->createMock(Response::class);
        $mockResponse->method('getBody')->willReturn($mockStream);
        $mockResponse->method('getStatusCode')->willReturn(404);

        $mockClient = $this->createMock(ClientInterface::class);
        $mockClient->method('send')->willReturn($mockResponse);

        $provider = $this->createProviderWithMockClient($mockClient);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Page not found');
        $provider->request('/nonexistent');
    }

    public function testRequestHandlesEmptyResponse(): void
    {
        // 模拟空响应
        $mockStream = $this->createMock(StreamInterface::class);
        $mockStream->method('getContents')->willReturn('');

        $mockResponse = $this->createMock(Response::class);
        $mockResponse->method('getBody')->willReturn($mockStream);
        $mockResponse->method('getStatusCode')->willReturn(200);

        $mockClient = $this->createMock(ClientInterface::class);
        $mockClient->method('send')->willReturn($mockResponse);

        $provider = $this->createProviderWithMockClient($mockClient);
        $result = $provider->request('/test');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testRequestHandlesInvalidJson(): void
    {
        // 模拟无效 JSON 响应
        $mockStream = $this->createMock(StreamInterface::class);
        $mockStream->method('getContents')->willReturn('not valid json');

        $mockResponse = $this->createMock(Response::class);
        $mockResponse->method('getBody')->willReturn($mockStream);
        $mockResponse->method('getStatusCode')->willReturn(200);

        $mockClient = $this->createMock(ClientInterface::class);
        $mockClient->method('send')->willReturn($mockResponse);

        $provider = $this->createProviderWithMockClient($mockClient);
        $result = $provider->request('/test');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testIsConnectedWithBlockID(): void
    {
        // 模拟包含 blockID 的响应
        $mockStream = $this->createMock(StreamInterface::class);
        $mockStream->method('getContents')
            ->willReturn('{"blockID":"0000000002b3b0d8"}')
        ;

        $mockResponse = $this->createMock(Response::class);
        $mockResponse->method('getBody')->willReturn($mockStream);
        $mockResponse->method('getStatusCode')->willReturn(200);

        $mockClient = $this->createMock(ClientInterface::class);
        $mockClient->method('send')->willReturn($mockResponse);

        $provider = $this->createProviderWithMockClient($mockClient);
        $this->assertTrue($provider->isConnected());
    }

    public function testIsConnectedWithStatus(): void
    {
        // 模拟包含 status 的响应
        $mockStream = $this->createMock(StreamInterface::class);
        $mockStream->method('getContents')
            ->willReturn('{"status":"ok"}')
        ;

        $mockResponse = $this->createMock(Response::class);
        $mockResponse->method('getBody')->willReturn($mockStream);
        $mockResponse->method('getStatusCode')->willReturn(200);

        $mockClient = $this->createMock(ClientInterface::class);
        $mockClient->method('send')->willReturn($mockResponse);

        $provider = $this->createProviderWithMockClient($mockClient);
        $this->assertTrue($provider->isConnected());
    }

    public function testIsConnectedReturnsFalse(): void
    {
        // 模拟不包含 blockID 或 status 的响应
        $mockStream = $this->createMock(StreamInterface::class);
        $mockStream->method('getContents')
            ->willReturn('{"data":"something"}')
        ;

        $mockResponse = $this->createMock(Response::class);
        $mockResponse->method('getBody')->willReturn($mockStream);
        $mockResponse->method('getStatusCode')->willReturn(200);

        $mockClient = $this->createMock(ClientInterface::class);
        $mockClient->method('send')->willReturn($mockResponse);

        $provider = $this->createProviderWithMockClient($mockClient);
        $this->assertFalse($provider->isConnected());
    }
}
