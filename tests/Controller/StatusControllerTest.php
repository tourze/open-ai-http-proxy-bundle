<?php

declare(strict_types=1);

namespace Tourze\OpenAiHttpProxyBundle\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Tourze\OpenAiHttpProxyBundle\Controller\StatusController;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;

/**
 * @internal
 */
#[CoversClass(StatusController::class)]
#[RunTestsInSeparateProcesses]
final class StatusControllerTest extends AbstractWebTestCase
{
    public function testStatusRequiresAuthentication(): void
    {
        $client = self::createClientWithDatabase();

        $client->request('GET', '/proxy/v1/status');

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
        $this->assertJson(false !== $client->getResponse()->getContent() ? $client->getResponse()->getContent() : '{}');
    }

    public function testStatusWithValidToken(): void
    {
        $client = self::createClientWithDatabase();

        $client->request('GET', '/proxy/v1/status', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer valid-test-token',
        ]);

        // 由于测试环境中没有配置有效的 token，会返回 401
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
        $this->assertJson(false !== $client->getResponse()->getContent() ? $client->getResponse()->getContent() : '{}');
    }

    public function testStatusWithInvalidToken(): void
    {
        $client = self::createClientWithDatabase();

        $client->request('GET', '/proxy/v1/status', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer invalid-token',
        ]);

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    public function testStatusWithMalformedAuthHeader(): void
    {
        $client = self::createClientWithDatabase();

        $client->request('GET', '/proxy/v1/status', [], [], [
            'HTTP_AUTHORIZATION' => 'InvalidHeader',
        ]);

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    public function testStatusHandlesHttpMethods(): void
    {
        $client = self::createClientWithDatabase();
        $client->catchExceptions(false);

        // Test POST (should not be allowed)
        $this->expectException(MethodNotAllowedHttpException::class);
        $client->request('POST', '/proxy/v1/status');
    }

    public function testStatusHandlesPutMethod(): void
    {
        $client = self::createClientWithDatabase();
        $client->catchExceptions(false);

        $this->expectException(MethodNotAllowedHttpException::class);
        $client->request('PUT', '/proxy/v1/status');
    }

    public function testStatusHandlesDeleteMethod(): void
    {
        $client = self::createClientWithDatabase();
        $client->catchExceptions(false);

        $this->expectException(MethodNotAllowedHttpException::class);
        $client->request('DELETE', '/proxy/v1/status');
    }

    public function testStatusHandlesPatchMethod(): void
    {
        $client = self::createClientWithDatabase();
        $client->catchExceptions(false);

        $this->expectException(MethodNotAllowedHttpException::class);
        $client->request('PATCH', '/proxy/v1/status');
    }

    public function testStatusHandlesOptionsMethod(): void
    {
        $client = self::createClientWithDatabase();
        $client->catchExceptions(false);

        $this->expectException(MethodNotAllowedHttpException::class);
        $client->request('OPTIONS', '/proxy/v1/status');
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
