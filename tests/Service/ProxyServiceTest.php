<?php

declare(strict_types=1);

namespace Tourze\OpenAiHttpProxyBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tourze\OpenAiHttpProxyBundle\Service\ProxyService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(ProxyService::class)]
#[RunTestsInSeparateProcesses]
final class ProxyServiceTest extends AbstractIntegrationTestCase
{
    private ProxyService $service;

    public function testForwardWithNoAvailableClient(): void
    {
        $response = $this->service->forward('/v1/chat/completions', ['model' => 'gpt-3.5-turbo']);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(503, $response->getStatusCode());

        $responseContent = $response->getContent();
        $content = json_decode(false !== $responseContent ? $responseContent : '', true);
        $this->assertIsArray($content);
        $this->assertArrayHasKey('error', $content);
        $this->assertIsArray($content['error']);
        $this->assertSame('No available OpenAI client for model: gpt-3.5-turbo', $content['error']['message']);
    }

    public function testForwardStreamWithNoAvailableClient(): void
    {
        $response = $this->service->forwardStream('/v1/chat/completions', ['model' => 'gpt-3.5-turbo']);

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertSame(503, $response->getStatusCode());
    }

    public function testGetMetricsReturnsEmptyArray(): void
    {
        $metrics = $this->service->getMetrics();
        $this->assertEmpty($metrics);
    }

    public function testGetPoolStatusReturnsArray(): void
    {
        $status = $this->service->getPoolStatus();
        $this->assertArrayHasKey('total_providers', $status);
        $this->assertArrayHasKey('total_clients', $status);
        $this->assertArrayHasKey('healthy_clients', $status);
        $this->assertArrayHasKey('clients', $status);
    }

    protected function onSetUp(): void
    {
        $this->service = self::getService(ProxyService::class);
    }
}
