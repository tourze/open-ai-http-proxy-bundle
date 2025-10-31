<?php

declare(strict_types=1);

namespace Tourze\OpenAiHttpProxyBundle\Service\Client;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Tourze\OpenAiContracts\Client\OpenAiCompatibleClientInterface;
use Tourze\OpenAiContracts\Provider\OpenAiClientProviderInterface;

#[WithMonologChannel(channel: 'open_ai_http_proxy')]
final class ClientPoolManager
{
    /**
     * @var array<int, array{client: OpenAiCompatibleClientInterface, provider: string, name: string, base_url: string, models: array<string>}>
     */
    private array $clientPool = [];

    private \DateTimeImmutable $lastRefresh;

    /**
     * @param iterable<OpenAiClientProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator(tag: OpenAiClientProviderInterface::TAG_NAME)]
        private readonly iterable $providers,
        private readonly LoggerInterface $logger,
    ) {
        $this->lastRefresh = new \DateTimeImmutable('1970-01-01');
    }

    public function refreshPoolIfNeeded(): void
    {
        $now = new \DateTimeImmutable();
        $diff = $now->getTimestamp() - $this->lastRefresh->getTimestamp();

        if ($diff > $this->getRefreshIntervalSeconds()) {
            $this->refreshPool();
        }
    }

    /**
     * @return array<int, array{client: OpenAiCompatibleClientInterface, provider: string, name: string, base_url: string, models: array<string>}>
     */
    public function getClientPool(): array
    {
        return $this->clientPool;
    }

    /**
     * @param array<int> $excludeList
     * @return array<int, array{client: OpenAiCompatibleClientInterface, provider: string, name: string, base_url: string, models: array<string>}>
     */
    public function getCandidatesForModel(string $model, array $excludeList = []): array
    {
        $candidates = [];

        foreach ($this->clientPool as $clientId => $poolEntry) {
            if (in_array($clientId, $excludeList, true)) {
                continue;
            }

            if ($this->supportsModel($poolEntry, $model)) {
                $candidates[$clientId] = $poolEntry;
            }
        }

        return $candidates;
    }

    public function getProviderCount(): int
    {
        return is_countable($this->providers) ? count($this->providers) : iterator_count($this->providers);
    }

    private function getRefreshIntervalSeconds(): int
    {
        $interval = $_ENV['OPENAI_PROXY_REFRESH_INTERVAL'] ?? '300';
        if (is_numeric($interval)) {
            return (int) $interval;
        }

        return 300;
    }

    private function refreshPool(): void
    {
        $this->clientPool = [];

        foreach ($this->providers as $provider) {
            try {
                foreach ($provider->fetchOpenAiClient() as $client) {
                    $clientId = spl_object_id($client);
                    $this->clientPool[$clientId] = [
                        'client' => $client,
                        'provider' => get_class($provider),
                        'name' => $client->getName(),
                        'base_url' => $client->getBaseUrl(),
                        'models' => $this->fetchSupportedModels($client),
                    ];
                }
            } catch (\Exception $e) {
                $this->logger->error('Failed to fetch clients from provider', [
                    'provider' => get_class($provider),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->lastRefresh = new \DateTimeImmutable();
    }

    /**
     * @return array<string>
     */
    private function fetchSupportedModels(OpenAiCompatibleClientInterface $client): array
    {
        try {
            $modelList = $client->listModels();
            $models = [];
            foreach ($modelList->getData() as $model) {
                $models[] = $model->getId();
            }

            return count($models) > 0 ? $models : ['gpt-3.5-turbo', 'gpt-4'];
        } catch (\Exception $e) {
            $this->logger->warning('Failed to fetch supported models from client', [
                'client' => $client->getName(),
                'error' => $e->getMessage(),
            ]);

            return ['gpt-3.5-turbo', 'gpt-4'];
        }
    }

    /**
     * @param array{client: OpenAiCompatibleClientInterface, provider: string, name: string, base_url: string, models: array<string>} $poolEntry
     */
    private function supportsModel(array $poolEntry, string $model): bool
    {
        if ([] === $poolEntry['models']) {
            return true;
        }

        foreach ($poolEntry['models'] as $supportedModel) {
            if (str_starts_with($model, $supportedModel) || str_starts_with($supportedModel, $model)) {
                return true;
            }
        }

        return false;
    }
}
