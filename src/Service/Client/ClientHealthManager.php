<?php

declare(strict_types=1);

namespace Tourze\OpenAiHttpProxyBundle\Service\Client;

use Tourze\OpenAiContracts\Client\OpenAiCompatibleClientInterface;

final class ClientHealthManager
{
    /**
     * @var array<int, array{is_healthy: bool, latency_ms: float, error: ?string, checked_at: \DateTimeImmutable}>
     */
    private array $clientHealthStatus = [];

    public function isHealthy(OpenAiCompatibleClientInterface $client): bool
    {
        $clientId = spl_object_id($client);

        if (!isset($this->clientHealthStatus[$clientId])) {
            return true;
        }

        $health = $this->clientHealthStatus[$clientId];

        if (false === $health['is_healthy']) {
            $timeSinceCheck = time() - $health['checked_at']->getTimestamp();
            if ($timeSinceCheck < 60) {
                return false;
            }
        }

        return true;
    }

    public function markUnhealthy(OpenAiCompatibleClientInterface $client, string $reason): void
    {
        $clientId = spl_object_id($client);
        $this->clientHealthStatus[$clientId] = [
            'is_healthy' => false,
            'error' => $reason,
            'checked_at' => new \DateTimeImmutable(),
            'latency_ms' => 0,
        ];
    }

    public function performHealthCheck(OpenAiCompatibleClientInterface $client): void
    {
        $clientId = spl_object_id($client);
        $this->clientHealthStatus[$clientId] = $this->checkHealth($client);
    }

    /**
     * @return array<int, array{is_healthy: bool, latency_ms: float, error: ?string, checked_at: \DateTimeImmutable}>
     */
    public function getHealthStatus(): array
    {
        return $this->clientHealthStatus;
    }

    /**
     * @return array{is_healthy: bool, latency_ms: float, error: ?string, checked_at: \DateTimeImmutable}
     */
    private function checkHealth(OpenAiCompatibleClientInterface $client): array
    {
        $startTime = microtime(true);
        $status = [
            'is_healthy' => false,
            'latency_ms' => 0.0,
            'error' => null,
            'checked_at' => new \DateTimeImmutable(),
        ];

        try {
            if ($client->isAvailable()) {
                $client->getBalance();
                $status['is_healthy'] = true;
            } else {
                $status['error'] = $client->getLastError() ?? 'Client not available';
            }
        } catch (\Exception $e) {
            $status['error'] = $e->getMessage();
        }

        $status['latency_ms'] = (microtime(true) - $startTime) * 1000;

        return $status;
    }
}
