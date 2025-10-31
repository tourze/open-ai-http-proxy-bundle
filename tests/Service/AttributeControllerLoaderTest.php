<?php

declare(strict_types=1);

namespace Tourze\OpenAiHttpProxyBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Config\Exception\LogicException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\Routing\RouteCollection;
use Tourze\OpenAiHttpProxyBundle\Controller\StatusController;
use Tourze\OpenAiHttpProxyBundle\Service\AttributeControllerLoader;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;

/**
 * @internal
 */
#[CoversClass(StatusController::class)]
#[RunTestsInSeparateProcesses]
final class AttributeControllerLoaderTest extends AbstractWebTestCase
{
    public function testSupportsMethod(): void
    {
        // AttributeControllerLoader 实现了 RoutingAutoLoaderInterface
        // 它的 supports 方法总是返回 false，因为它通过 autoload 方法自动加载路由
        $loader = new AttributeControllerLoader();
        $this->assertFalse($loader->supports('resource', 'attribute'));
        $this->assertFalse($loader->supports('resource', 'annotation'));
        $this->assertFalse($loader->supports('resource', 'yaml'));
        $this->assertFalse($loader->supports('resource', 'xml'));
    }

    public function testLoadReturnsRouteCollection(): void
    {
        $loader = new AttributeControllerLoader();
        $result = $loader->load(__DIR__ . '/../../src/Controller', 'attribute');

        $this->assertInstanceOf(RouteCollection::class, $result);
    }

    public function testGetResolverMethod(): void
    {
        // 初始状态下没有设置 resolver，getResolver 会抛出异常
        $loader = new AttributeControllerLoader();
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot get a resolver if none was set.');

        $loader->getResolver();
    }

    public function testSetResolverMethod(): void
    {
        // 测试设置resolver的完整流程
        // 通过HTTP请求触发路由加载，验证resolver设置行为
        $client = self::createClientWithDatabase();

        // 发送请求到状态端点，触发路由系统初始化
        $client->request('GET', '/proxy/v1/status');

        // 验证路由系统正常工作，说明resolver设置正确
        // 即使返回401（未授权），也说明路由系统工作正常
        $this->assertNotEquals(404, $client->getResponse()->getStatusCode());
    }

    public function testAutoload(): void
    {
        // Test the autoload method functionality
        $loader = new AttributeControllerLoader();
        $result = $loader->autoload();

        // The autoload method returns a RouteCollection
        $this->assertInstanceOf(RouteCollection::class, $result);
    }

    #[DataProvider('provideNotAllowedMethods')]
    public function testMethodNotAllowed(string $method): void
    {
        $client = self::createClientWithDatabase();
        $client->catchExceptions(false);

        $this->expectException(MethodNotAllowedHttpException::class);

        match ($method) {
            'POST' => $client->request('POST', '/proxy/v1/status'),
            'PUT' => $client->request('PUT', '/proxy/v1/status'),
            'DELETE' => $client->request('DELETE', '/proxy/v1/status'),
            'PATCH' => $client->request('PATCH', '/proxy/v1/status'),
            'OPTIONS' => $client->request('OPTIONS', '/proxy/v1/status'),
            'TRACE' => $client->request('TRACE', '/proxy/v1/status'),
            'PURGE' => $client->request('PURGE', '/proxy/v1/status'),
            default => $client->request('INVALID', '/proxy/v1/status'),
        };
    }
}
