<?php

declare(strict_types=1);

namespace Tourze\OpenAiHttpProxyBundle\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Tourze\OpenAiHttpProxyBundle\Controller\EmbeddingsController;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;

/**
 * @internal
 */
#[CoversClass(EmbeddingsController::class)]
#[RunTestsInSeparateProcesses]
final class EmbeddingsControllerTest extends AbstractWebTestCase
{
    public function testEmbeddingsRequiresAuthentication(): void
    {
        $client = self::createClientWithDatabase();

        $client->request('POST', '/proxy/v1/embeddings', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], (string) json_encode([
            'model' => 'text-embedding-ada-002',
            'input' => 'The quick brown fox jumps over the lazy dog',
        ]));

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    public function testEmbeddingsWithInvalidJson(): void
    {
        $client = self::createClientWithDatabase();

        $client->request('POST', '/proxy/v1/embeddings', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer test-token',
        ], 'invalid-json');

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    public function testEmbeddingsWithValidRequest(): void
    {
        $client = self::createClientWithDatabase();

        $client->request('POST', '/proxy/v1/embeddings', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer valid-token',
        ], (string) json_encode([
            'model' => 'text-embedding-ada-002',
            'input' => ['The quick brown fox', 'jumps over the lazy dog'],
        ]));

        // 由于需要真实的 OpenAI 客户端，这里会返回认证错误
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    public function testEmbeddingsWithGetMethod(): void
    {
        $client = self::createClientWithDatabase();
        $client->catchExceptions(false);

        $this->expectException(MethodNotAllowedHttpException::class);

        $client->request('GET', '/proxy/v1/embeddings');
    }

    public function testEmbeddingsWithPutMethod(): void
    {
        $client = self::createClientWithDatabase();
        $client->catchExceptions(false);

        $this->expectException(MethodNotAllowedHttpException::class);

        $client->request('PUT', '/proxy/v1/embeddings', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], (string) json_encode(['test' => 'data']));
    }

    public function testEmbeddingsWithDeleteMethod(): void
    {
        $client = self::createClientWithDatabase();
        $client->catchExceptions(false);

        $this->expectException(MethodNotAllowedHttpException::class);

        $client->request('DELETE', '/proxy/v1/embeddings');
    }

    public function testEmbeddingsWithPatchMethod(): void
    {
        $client = self::createClientWithDatabase();
        $client->catchExceptions(false);

        $this->expectException(MethodNotAllowedHttpException::class);

        $client->request('PATCH', '/proxy/v1/embeddings', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], (string) json_encode(['test' => 'data']));
    }

    public function testEmbeddingsWithOptionsMethod(): void
    {
        $client = self::createClientWithDatabase();
        $client->catchExceptions(false);

        $this->expectException(MethodNotAllowedHttpException::class);

        $client->request('OPTIONS', '/proxy/v1/embeddings');
    }

    #[DataProvider('provideNotAllowedMethods')]
    public function testMethodNotAllowed(string $method): void
    {
        $client = self::createClientWithDatabase();
        $client->catchExceptions(false);

        $this->expectException(MethodNotAllowedHttpException::class);

        match ($method) {
            'GET' => $client->request('GET', '/proxy/v1/embeddings'),
            'PUT' => $client->request('PUT', '/proxy/v1/embeddings'),
            'DELETE' => $client->request('DELETE', '/proxy/v1/embeddings'),
            'PATCH' => $client->request('PATCH', '/proxy/v1/embeddings'),
            'OPTIONS' => $client->request('OPTIONS', '/proxy/v1/embeddings'),
            'TRACE' => $client->request('TRACE', '/proxy/v1/embeddings'),
            'PURGE' => $client->request('PURGE', '/proxy/v1/embeddings'),
            default => $client->request('INVALID', '/proxy/v1/embeddings'),
        };
    }
}
