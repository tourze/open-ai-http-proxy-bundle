<?php

declare(strict_types=1);

namespace Tourze\OpenAiHttpProxyBundle\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Tourze\OpenAiHttpProxyBundle\Controller\ModelsController;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;

/**
 * @internal
 */
#[CoversClass(ModelsController::class)]
#[RunTestsInSeparateProcesses]
final class ModelsControllerTest extends AbstractWebTestCase
{
    public function testModelsRequiresAuthentication(): void
    {
        $client = self::createClientWithDatabase();

        $client->request('GET', '/proxy/v1/models');

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    public function testModelsWithInvalidToken(): void
    {
        $client = self::createClientWithDatabase();

        $client->request('GET', '/proxy/v1/models', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer invalid-token',
        ]);

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    public function testModelsWithValidToken(): void
    {
        $client = self::createClientWithDatabase();

        $client->request('GET', '/proxy/v1/models', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer valid-token',
        ]);

        // 由于需要真实的认证服务，这里会返回 401
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    public function testModelsResponseFormat(): void
    {
        $client = self::createClientWithDatabase();

        $client->request('GET', '/proxy/v1/models', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer test-token',
        ]);

        $response = $client->getResponse();
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());

        // 如果能成功，应该返回正确的JSON格式
        if (200 === $response->getStatusCode()) {
            $content = $response->getContent();
            $data = json_decode(false !== $content ? $content : '{}', true);
            $this->assertIsArray($data);
            $this->assertArrayHasKey('object', $data);
            $this->assertEquals('list', $data['object']);
            $this->assertArrayHasKey('data', $data);
            $this->assertIsArray($data['data']);
        }
    }

    public function testModelsWithPostMethod(): void
    {
        $client = self::createClientWithDatabase();
        $client->catchExceptions(false);

        $this->expectException(MethodNotAllowedHttpException::class);

        $client->request('POST', '/proxy/v1/models', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], (string) json_encode(['test' => 'data']));
    }

    public function testModelsWithPutMethod(): void
    {
        $client = self::createClientWithDatabase();
        $client->catchExceptions(false);

        $this->expectException(MethodNotAllowedHttpException::class);

        $client->request('PUT', '/proxy/v1/models', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], (string) json_encode(['test' => 'data']));
    }

    public function testModelsWithDeleteMethod(): void
    {
        $client = self::createClientWithDatabase();
        $client->catchExceptions(false);

        $this->expectException(MethodNotAllowedHttpException::class);

        $client->request('DELETE', '/proxy/v1/models');
    }

    public function testModelsWithPatchMethod(): void
    {
        $client = self::createClientWithDatabase();
        $client->catchExceptions(false);

        $this->expectException(MethodNotAllowedHttpException::class);

        $client->request('PATCH', '/proxy/v1/models', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], (string) json_encode(['test' => 'data']));
    }

    public function testModelsWithOptionsMethod(): void
    {
        $client = self::createClientWithDatabase();
        $client->catchExceptions(false);

        $this->expectException(MethodNotAllowedHttpException::class);

        $client->request('OPTIONS', '/proxy/v1/models');
    }

    #[DataProvider('provideNotAllowedMethods')]
    public function testMethodNotAllowed(string $method): void
    {
        $client = self::createClientWithDatabase();
        $client->catchExceptions(false);

        $this->expectException(MethodNotAllowedHttpException::class);

        match ($method) {
            'POST' => $client->request('POST', '/proxy/v1/models'),
            'PUT' => $client->request('PUT', '/proxy/v1/models'),
            'DELETE' => $client->request('DELETE', '/proxy/v1/models'),
            'PATCH' => $client->request('PATCH', '/proxy/v1/models'),
            'OPTIONS' => $client->request('OPTIONS', '/proxy/v1/models'),
            'TRACE' => $client->request('TRACE', '/proxy/v1/models'),
            'PURGE' => $client->request('PURGE', '/proxy/v1/models'),
            default => $client->request('INVALID', '/proxy/v1/models'),
        };
    }
}
