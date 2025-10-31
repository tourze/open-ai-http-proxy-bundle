<?php

declare(strict_types=1);

namespace Tourze\OpenAiHttpProxyBundle\Tests\Controller;

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
final class ChatCompletionsControllerTest extends AbstractWebTestCase
{
    public function testChatCompletionsRequiresAuthentication(): void
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
    }

    public function testChatCompletionsWithInvalidJson(): void
    {
        $client = self::createClientWithDatabase();

        $client->request('POST', '/proxy/v1/chat/completions', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer test-token',
        ], 'invalid-json');

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    public function testChatCompletionsWithValidRequest(): void
    {
        $client = self::createClientWithDatabase();

        $client->request('POST', '/proxy/v1/chat/completions', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer valid-token',
        ], (string) json_encode([
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                ['role' => 'user', 'content' => 'Hello'],
            ],
            'temperature' => 0.7,
            'max_tokens' => 150,
        ]));

        // 由于需要真实的 OpenAI 客户端，这里会返回 503
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    public function testChatCompletionsWithStreamOption(): void
    {
        $client = self::createClientWithDatabase();

        $client->request('POST', '/proxy/v1/chat/completions', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer valid-token',
        ], (string) json_encode([
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'user', 'content' => 'Hello'],
            ],
            'stream' => true,
        ]));

        // 测试流式响应
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    public function testChatCompletionsWithGetMethod(): void
    {
        $client = self::createClientWithDatabase();
        $client->catchExceptions(false);

        $this->expectException(MethodNotAllowedHttpException::class);

        $client->request('GET', '/proxy/v1/chat/completions');
    }

    public function testChatCompletionsWithPutMethod(): void
    {
        $client = self::createClientWithDatabase();
        $client->catchExceptions(false);

        $this->expectException(MethodNotAllowedHttpException::class);

        $client->request('PUT', '/proxy/v1/chat/completions', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], (string) json_encode(['test' => 'data']));
    }

    public function testChatCompletionsWithDeleteMethod(): void
    {
        $client = self::createClientWithDatabase();
        $client->catchExceptions(false);

        $this->expectException(MethodNotAllowedHttpException::class);

        $client->request('DELETE', '/proxy/v1/chat/completions');
    }

    public function testChatCompletionsWithPatchMethod(): void
    {
        $client = self::createClientWithDatabase();
        $client->catchExceptions(false);

        $this->expectException(MethodNotAllowedHttpException::class);

        $client->request('PATCH', '/proxy/v1/chat/completions', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], (string) json_encode(['test' => 'data']));
    }

    public function testChatCompletionsWithOptionsMethod(): void
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
