<?php

declare(strict_types=1);

namespace Tourze\OpenAiHttpProxyBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Tourze\OpenAiHttpProxyBundle\Controller\ChatCompletionsController;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;

/**
 * @internal
 */
#[CoversClass(ChatCompletionsController::class)]
#[RunTestsInSeparateProcesses]
final class ProxyControllerTest extends AbstractWebTestCase
{
    public function testChatCompletionsWithoutAuth(): void
    {
        $client = self::createClientWithDatabase();

        $client->request('POST', '/proxy/v1/chat/completions', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], (string) json_encode([
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'user', 'content' => 'Hello'],
            ],
        ]));

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
        $content = $client->getResponse()->getContent();
        $response = json_decode(false !== $content ? $content : '{}', true);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('error', $response);
    }

    public function testChatCompletionsWithInvalidToken(): void
    {
        $client = self::createClientWithDatabase();

        $client->request('POST', '/proxy/v1/chat/completions', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer invalid-token',
        ], (string) json_encode([
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'user', 'content' => 'Hello'],
            ],
        ]));

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    public function testModelsEndpoint(): void
    {
        $client = self::createClientWithDatabase();

        $client->request('GET', '/proxy/v1/models', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer test-token',
        ]);

        // 由于没有有效的token，应该返回401
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    public function testInvalidJsonRequest(): void
    {
        $client = self::createClientWithDatabase();

        $client->request('POST', '/proxy/v1/chat/completions', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer test-token',
        ], 'invalid-json');

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
        $content = $client->getResponse()->getContent();
        $response = json_decode(false !== $content ? $content : '{}', true);
        $this->assertIsArray($response);
        if (isset($response['error'])) {
            $this->assertEquals('Invalid token', $response['error']);
        }
    }

    public function testProxyWithPutMethod(): void
    {
        $client = self::createClientWithDatabase();
        $client->catchExceptions(false);

        $this->expectException(MethodNotAllowedHttpException::class);

        $client->request('PUT', '/proxy/v1/chat/completions', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], (string) json_encode(['test' => 'data']));
    }

    public function testProxyWithDeleteMethod(): void
    {
        $client = self::createClientWithDatabase();
        $client->catchExceptions(false);

        $this->expectException(MethodNotAllowedHttpException::class);

        $client->request('DELETE', '/proxy/v1/chat/completions');
    }

    public function testProxyWithPatchMethod(): void
    {
        $client = self::createClientWithDatabase();
        $client->catchExceptions(false);

        $this->expectException(MethodNotAllowedHttpException::class);

        $client->request('PATCH', '/proxy/v1/chat/completions', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], (string) json_encode(['test' => 'data']));
    }

    public function testProxyWithOptionsMethod(): void
    {
        $client = self::createClientWithDatabase();
        $client->catchExceptions(false);

        $this->expectException(MethodNotAllowedHttpException::class);

        $client->request('OPTIONS', '/proxy/v1/chat/completions');
    }

    #[DataProvider('provideNotAllowedMethods')]
    public function testMethodNotAllowed(string $method): void
    {
        $client = self::createClientWithDatabase();
        $client->catchExceptions(false);

        $this->expectException(MethodNotAllowedHttpException::class);

        match ($method) {
            'GET' => $client->request('GET', '/proxy/v1/chat/completions'),
            'PUT' => $client->request('PUT', '/proxy/v1/chat/completions'),
            'DELETE' => $client->request('DELETE', '/proxy/v1/chat/completions'),
            'PATCH' => $client->request('PATCH', '/proxy/v1/chat/completions'),
            'OPTIONS' => $client->request('OPTIONS', '/proxy/v1/chat/completions'),
            'TRACE' => $client->request('TRACE', '/proxy/v1/chat/completions'),
            'PURGE' => $client->request('PURGE', '/proxy/v1/chat/completions'),
            default => $client->request('INVALID', '/proxy/v1/chat/completions'),
        };
    }
}
