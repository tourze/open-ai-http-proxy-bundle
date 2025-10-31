<?php

declare(strict_types=1);

namespace Tourze\OpenAiHttpProxyBundle\Tests\Service\Client;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\OpenAiHttpProxyBundle\Service\Client\ClientPoolManager;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(ClientPoolManager::class)]
#[RunTestsInSeparateProcesses]
final class ClientPoolManagerTest extends AbstractIntegrationTestCase
{
    private ClientPoolManager $poolManager;

    public function testGetClientPoolWithNoProviders(): void
    {
        $pool = $this->poolManager->getClientPool();

        // Pool is guaranteed to be array by return type
        $this->assertEmpty($pool);
    }

    public function testGetCandidatesForModel(): void
    {
        $candidates = $this->poolManager->getCandidatesForModel('gpt-3.5-turbo');

        // Candidates is guaranteed to be array by return type
        $this->assertEmpty($candidates);
    }

    public function testGetCandidatesWithExcludeList(): void
    {
        $candidates = $this->poolManager->getCandidatesForModel('gpt-3.5-turbo', [1, 2, 3]);

        // Candidates is guaranteed to be array by return type
        $this->assertEmpty($candidates);
    }

    public function testGetProviderCount(): void
    {
        $count = $this->poolManager->getProviderCount();

        // Count is guaranteed to be int by return type
        $this->assertSame(0, $count);
    }

    public function testRefreshPoolIfNeeded(): void
    {
        // Get initial state
        $initialPool = $this->poolManager->getClientPool();
        $initialCount = $this->poolManager->getProviderCount();

        // Should not throw exception and maintain consistent state
        $this->poolManager->refreshPoolIfNeeded();

        // Verify state consistency after refresh
        $afterRefreshPool = $this->poolManager->getClientPool();
        $afterRefreshCount = $this->poolManager->getProviderCount();

        $this->assertSame($initialPool, $afterRefreshPool);
        $this->assertSame($initialCount, $afterRefreshCount);
    }

    protected function onSetUp(): void
    {
        $this->poolManager = self::getService(ClientPoolManager::class);
    }
}
