<?php

declare(strict_types=1);

namespace Tourze\OpenAiHttpProxyBundle\Tests\Service\Client;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\OpenAiHttpProxyBundle\Service\Client\ClientStatsManager;

/**
 * @internal
 */
#[CoversClass(ClientStatsManager::class)]
final class ClientStatsManagerTest extends TestCase
{
    private ClientStatsManager $statsManager;

    protected function setUp(): void
    {
        $this->statsManager = new ClientStatsManager();
    }

    public function testRecordSuccessfulRequest(): void
    {
        $client = new TestOpenAiCompatibleClient();

        $this->statsManager->recordRequest($client, 100.0, true);

        $clientId = spl_object_id($client);
        $stats = $this->statsManager->getStats($clientId);

        $this->assertSame(1, $stats['total_requests']);
        $this->assertSame(1, $stats['successful_requests']);
        $this->assertSame(0, $stats['failed_requests']);
        $this->assertSame(0, $stats['consecutive_failures']);
    }

    public function testRecordFailedRequest(): void
    {
        $client = new TestOpenAiCompatibleClient();

        $this->statsManager->recordRequest($client, 200.0, false);

        $clientId = spl_object_id($client);
        $stats = $this->statsManager->getStats($clientId);

        $this->assertSame(1, $stats['total_requests']);
        $this->assertSame(0, $stats['successful_requests']);
        $this->assertSame(1, $stats['failed_requests']);
        $this->assertSame(1, $stats['consecutive_failures']);
    }

    public function testHasConsecutiveFailures(): void
    {
        $client = new TestOpenAiCompatibleClient();
        $clientId = spl_object_id($client);

        $this->assertFalse($this->statsManager->hasConsecutiveFailures($clientId));

        // Record 3 failed requests
        $this->statsManager->recordRequest($client, 100.0, false);
        $this->statsManager->recordRequest($client, 100.0, false);
        $this->statsManager->recordRequest($client, 100.0, false);

        $this->assertTrue($this->statsManager->hasConsecutiveFailures($clientId));
    }

    public function testGetAllStats(): void
    {
        $allStats = $this->statsManager->getAllStats();

        // AllStats is guaranteed to be array by return type
        $this->assertEmpty($allStats);
    }

    public function testRecordRequest(): void
    {
        $client = new TestOpenAiCompatibleClient();

        // Test the actual recordRequest method
        $this->statsManager->recordRequest($client, 150.0, true);

        $clientId = spl_object_id($client);
        $stats = $this->statsManager->getStats($clientId);

        $this->assertSame(1, $stats['total_requests']);
        $this->assertSame(1, $stats['successful_requests']);
        $this->assertSame(150.0, $stats['total_latency_ms']);
    }
}
