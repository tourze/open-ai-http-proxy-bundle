<?php

declare(strict_types=1);

namespace Tourze\OpenAiHttpProxyBundle\Service\Client;

use Tourze\OpenAiContracts\Client\OpenAiCompatibleClientInterface;

final class ClientStatsManager
{
    /**
     * @var array<int, array{total_requests: int, successful_requests: int, failed_requests: int, total_latency_ms: float, last_used: ?\DateTimeImmutable, consecutive_failures: int, score: float}>
     */
    private array $clientStats = [];

    public function recordRequest(OpenAiCompatibleClientInterface $client, float $latencyMs, bool $success): void
    {
        $clientId = spl_object_id($client);

        if (!isset($this->clientStats[$clientId])) {
            $this->initializeClientStats($clientId);
        }

        $stats = $this->clientStats[$clientId];
        ++$stats['total_requests'];
        $stats['last_used'] = new \DateTimeImmutable();

        if ($success) {
            ++$stats['successful_requests'];
            $stats['total_latency_ms'] += $latencyMs;
            $stats['consecutive_failures'] = 0;
        } else {
            ++$stats['failed_requests'];
            ++$stats['consecutive_failures'];
        }

        $this->clientStats[$clientId] = $stats;
    }

    /**
     * @return array{total_requests: int, successful_requests: int, failed_requests: int, total_latency_ms: float, last_used: ?\DateTimeImmutable, consecutive_failures: int, score: float}
     */
    public function getStats(int $clientId): array
    {
        if (!isset($this->clientStats[$clientId])) {
            $this->initializeClientStats($clientId);
        }

        return $this->clientStats[$clientId];
    }

    /**
     * @return array<int, array{total_requests: int, successful_requests: int, failed_requests: int, total_latency_ms: float, last_used: ?\DateTimeImmutable, consecutive_failures: int, score: float}>
     */
    public function getAllStats(): array
    {
        return $this->clientStats;
    }

    public function hasConsecutiveFailures(int $clientId, int $threshold = 3): bool
    {
        return ($this->clientStats[$clientId]['consecutive_failures'] ?? 0) >= $threshold;
    }

    private function initializeClientStats(int $clientId): void
    {
        $this->clientStats[$clientId] = [
            'total_requests' => 0,
            'successful_requests' => 0,
            'failed_requests' => 0,
            'total_latency_ms' => 0.0,
            'last_used' => null,
            'consecutive_failures' => 0,
            'score' => 100.0,
        ];
    }
}
