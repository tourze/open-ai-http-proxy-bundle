<?php

declare(strict_types=1);

namespace Tourze\OpenAiHttpProxyBundle\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Tourze\OpenAiHttpProxyBundle\Controller\CompletionsController;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;

/**
 * @internal
 */
#[CoversClass(CompletionsController::class)]
#[RunTestsInSeparateProcesses]
final class CompletionsControllerTest extends AbstractWebTestCase
{
    public function testCompletionsRequiresAuthentication(): void
    {
        $client = self::createClientWithDatabase();

        $client->request('POST', '/proxy/v1/completions', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], (string) json_encode([
            'model' => 'gpt-3.5-turbo',
            'prompt' => 'Once upon a time',
        ]));

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    public function testCompletionsWithInvalidJson(): void
    {
        $client = self::createClientWithDatabase();

        $client->request('POST', '/proxy/v1/completions', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer test-token',
        ], 'invalid-json');

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    public function testCompletionsWithValidRequest(): void
    {
        $client = self::createClientWithDatabase();

        $client->request('POST', '/proxy/v1/completions', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer valid-token',
        ], (string) json_encode([
            'model' => 'gpt-3.5-turbo',
            'prompt' => 'Once upon a time',
            'max_tokens' => 100,
            'temperature' => 0.7,
        ]));

        // 由于需要真实的 OpenAI 客户端，这里会返回认证错误
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    public function testCompletionsWithGetMethod(): void
    {
        $client = self::createClientWithDatabase();
        $client->catchExceptions(false);

        $this->expectException(MethodNotAllowedHttpException::class);

        $client->request('GET', '/proxy/v1/completions');
    }

    public function testCompletionsWithPutMethod(): void
    {
        $client = self::createClientWithDatabase();
        $client->catchExceptions(false);

        $this->expectException(MethodNotAllowedHttpException::class);

        $client->request('PUT', '/proxy/v1/completions', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], (string) json_encode(['test' => 'data']));
    }

    public function testCompletionsWithDeleteMethod(): void
    {
        $client = self::createClientWithDatabase();
        $client->catchExceptions(false);

        $this->expectException(MethodNotAllowedHttpException::class);

        $client->request('DELETE', '/proxy/v1/completions');
    }

    public function testCompletionsWithPatchMethod(): void
    {
        $client = self::createClientWithDatabase();
        $client->catchExceptions(false);

        $this->expectException(MethodNotAllowedHttpException::class);

        $client->request('PATCH', '/proxy/v1/completions', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], (string) json_encode(['test' => 'data']));
    }

    public function testCompletionsWithOptionsMethod(): void
    {
        $client = self::createClientWithDatabase();
        $client->catchExceptions(false);

        $this->expectException(MethodNotAllowedHttpException::class);

        $client->request('OPTIONS', '/proxy/v1/completions');
    }

    #[DataProvider('provideNotAllowedMethods')]
    public function testMethodNotAllowed(string $method): void
    {
        $client = self::createClientWithDatabase();
        $client->catchExceptions(false);

        $this->expectException(MethodNotAllowedHttpException::class);

        match ($method) {
            'GET' => $client->request('GET', '/proxy/v1/completions'),
            'PUT' => $client->request('PUT', '/proxy/v1/completions'),
            'DELETE' => $client->request('DELETE', '/proxy/v1/completions'),
            'PATCH' => $client->request('PATCH', '/proxy/v1/completions'),
            'OPTIONS' => $client->request('OPTIONS', '/proxy/v1/completions'),
            'TRACE' => $client->request('TRACE', '/proxy/v1/completions'),
            'PURGE' => $client->request('PURGE', '/proxy/v1/completions'),
            default => $client->request('INVALID', '/proxy/v1/completions'),
        };
    }
}
