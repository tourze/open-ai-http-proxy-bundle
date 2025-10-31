<?php

declare(strict_types=1);

namespace Tourze\OpenAiHttpProxyBundle\Tests\Service\Client;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\OpenAiContracts\DTO\Balance;
use Tourze\OpenAiContracts\Response\BalanceResponseInterface;
use Tourze\OpenAiHttpProxyBundle\Service\Client\ClientHealthManager;

/**
 * @internal
 */
#[CoversClass(ClientHealthManager::class)]
final class ClientHealthManagerTest extends TestCase
{
    private ClientHealthManager $healthManager;

    protected function setUp(): void
    {
        $this->healthManager = new ClientHealthManager();
    }

    public function testIsHealthyWithNewClient(): void
    {
        $client = new TestOpenAiCompatibleClient();

        $this->assertTrue($this->healthManager->isHealthy($client));
    }

    public function testMarkUnhealthy(): void
    {
        $client = new TestOpenAiCompatibleClient();

        $this->healthManager->markUnhealthy($client, 'Test error');

        $this->assertFalse($this->healthManager->isHealthy($client));
    }

    public function testGetHealthStatus(): void
    {
        $status = $this->healthManager->getHealthStatus();

        // Status is guaranteed to be array by return type
        $this->assertEmpty($status);
    }

    public function testPerformHealthCheck(): void
    {
        $balanceResponse = new class implements BalanceResponseInterface {
            public function getBalance(): Balance
            {
                throw new \BadMethodCallException('Not used');
            }

            public static function fromArray(array $data): static
            {
                throw new \BadMethodCallException('Not used');
            }

            public function toArray(): array
            {
                return [];
            }

            public function getId(): ?string
            {
                return null;
            }

            public function getObject(): ?string
            {
                return null;
            }

            public function getCreated(): ?int
            {
                return null;
            }
        };

        $client = new TestOpenAiCompatibleClientWithBalance($balanceResponse);

        $this->healthManager->performHealthCheck($client);

        $this->assertTrue($this->healthManager->isHealthy($client));
    }
}
