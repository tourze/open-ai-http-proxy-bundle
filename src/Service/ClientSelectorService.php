<?php

declare(strict_types=1);

namespace Tourze\OpenAiHttpProxyBundle\Service;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Tourze\OpenAiContracts\Client\OpenAiCompatibleClientInterface;
use Tourze\OpenAiHttpProxyBundle\Service\Client\ClientHealthManager;
use Tourze\OpenAiHttpProxyBundle\Service\Client\ClientPoolManager;
use Tourze\OpenAiHttpProxyBundle\Service\Client\ClientStatsManager;
use Tourze\OpenAiHttpProxyBundle\Service\Client\SelectionStrategy\RandomSelectionStrategy;

#[WithMonologChannel(channel: 'open_ai_http_proxy')]
final readonly class ClientSelectorService
{
    public function __construct(
        private ClientPoolManager $poolManager,
        private ClientHealthManager $healthManager,
        private ClientStatsManager $statsManager,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function selectClientWithFallback(string $model, array $context = []): ?OpenAiCompatibleClientInterface
    {
        $maxRetries = $context['max_retries'] ?? 3;
        $excludeList = [];

        for ($i = 0; $i < $maxRetries; ++$i) {
            $context['exclude'] = $excludeList;
            $client = $this->selectClient($model, $context);

            if (null === $client) {
                return null;
            }

            if (
                $this->healthManager->isHealthy($client)
                && !$this->statsManager->hasConsecutiveFailures(spl_object_id($client))
            ) {
                return $client;
            }

            $excludeList[] = spl_object_id($client);
            $this->healthManager->markUnhealthy($client, 'Failed health check');
        }

        return null;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function selectClient(string $model, array $context = []): ?OpenAiCompatibleClientInterface
    {
        $this->poolManager->refreshPoolIfNeeded();

        $excludeClients = $context['exclude'] ?? [];
        $excludeList = [];
        if (is_array($excludeClients)) {
            foreach ($excludeClients as $clientId) {
                if (is_int($clientId)) {
                    $excludeList[] = $clientId;
                }
            }
        }
        $candidates = $this->poolManager->getCandidatesForModel($model, $excludeList);

        if ([] === $candidates) {
            $this->logger->warning('No available clients for model', ['model' => $model]);

            return null;
        }

        $strategy = new RandomSelectionStrategy();

        return $strategy->select($candidates, $context);
    }

    public function recordRequest(OpenAiCompatibleClientInterface $client, float $latencyMs, bool $success): void
    {
        $this->statsManager->recordRequest($client, $latencyMs, $success);
    }

    /**
     * @return array{total_providers: int, total_clients: int, healthy_clients: int, clients: array<array{name: string, provider: string, base_url: string, models: mixed, is_healthy: bool, stats: mixed}>}
     */
    public function getPoolStatus(): array
    {
        $this->poolManager->refreshPoolIfNeeded();
        $clientPool = $this->poolManager->getClientPool();
        $healthStatus = $this->healthManager->getHealthStatus();
        $allStats = $this->statsManager->getAllStats();

        $status = [
            'total_providers' => $this->poolManager->getProviderCount(),
            'total_clients' => count($clientPool),
            'healthy_clients' => 0,
            'clients' => [],
        ];

        foreach ($clientPool as $clientId => $poolEntry) {
            $health = $healthStatus[$clientId] ?? ['is_healthy' => true];
            $stats = $allStats[$clientId] ?? [];

            if ($health['is_healthy']) {
                ++$status['healthy_clients'];
            }

            $status['clients'][] = [
                'name' => $poolEntry['name'],
                'provider' => $poolEntry['provider'],
                'base_url' => $poolEntry['base_url'],
                'models' => $poolEntry['models'],
                'is_healthy' => $health['is_healthy'],
                'stats' => $stats,
            ];
        }

        return $status;
    }
}
