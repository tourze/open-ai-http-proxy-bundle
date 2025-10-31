<?php

declare(strict_types=1);

namespace Tourze\OpenAiHttpProxyBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\OpenAiHttpProxyBundle\Service\ClientSelectorService;
use Tourze\OpenAiHttpProxyBundle\Tests\Service\Client\TestOpenAiCompatibleClient;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(ClientSelectorService::class)]
#[RunTestsInSeparateProcesses]
final class ClientSelectorServiceTest extends AbstractIntegrationTestCase
{
    private ClientSelectorService $service;

    public function testSelectClientWithNoProvidersReturnsNull(): void
    {
        $client = $this->service->selectClient('gpt-3.5-turbo');

        $this->assertNull($client);
    }

    public function testSelectClientWithFallbackReturnsNullWhenNoClients(): void
    {
        $client = $this->service->selectClientWithFallback('gpt-3.5-turbo');

        $this->assertNull($client);
    }

    public function testGetPoolStatusWithEmptyPool(): void
    {
        $status = $this->service->getPoolStatus();

        // Status is guaranteed to be array by return type
        $this->assertArrayHasKey('total_providers', $status);
        $this->assertArrayHasKey('total_clients', $status);
        $this->assertArrayHasKey('healthy_clients', $status);
        $this->assertArrayHasKey('clients', $status);

        $this->assertSame(0, $status['total_providers']);
        $this->assertSame(0, $status['total_clients']);
        $this->assertSame(0, $status['healthy_clients']);
        $this->assertEmpty($status['clients']);
    }

    public function testRecordRequestWithMockClient(): void
    {
        $mockClient = new TestOpenAiCompatibleClient();

        // Should not throw exception - if it throws, the test will fail
        $this->service->recordRequest($mockClient, 100.0, true);
        $this->service->recordRequest($mockClient, 200.0, false);

        // Verify the service still functions after recording requests
        $status = $this->service->getPoolStatus();
        // Status is guaranteed to be array by return type
        $this->assertIsArray($status);
    }

    public function testSelectClientWithDifferentStrategies(): void
    {
        // Test with empty pool - all strategies should return null
        $strategies = [
            'round_robin',
            'random',
            'least_used',
            'best_performance',
            'weighted_score',
            'failover',
            'unknown_strategy',
        ];

        foreach ($strategies as $strategy) {
            $client = $this->service->selectClient('gpt-3.5-turbo', ['strategy' => $strategy]);
            $this->assertNull($client, "Strategy {$strategy} should return null with empty pool");
        }
    }

    public function testSelectClientWithExcludeList(): void
    {
        $context = [
            'exclude' => [1, 2, 3],
            'strategy' => 'random',
        ];

        $client = $this->service->selectClient('gpt-3.5-turbo', $context);

        $this->assertNull($client);
    }

    public function testSelectClientWithMaxRetries(): void
    {
        $context = [
            'max_retries' => 5,
        ];

        $client = $this->service->selectClientWithFallback('gpt-3.5-turbo', $context);

        $this->assertNull($client);
    }

    protected function onSetUp(): void
    {
        $this->service = self::getService(ClientSelectorService::class);
    }
}
