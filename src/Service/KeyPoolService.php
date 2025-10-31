<?php

declare(strict_types=1);

namespace Tourze\OpenAiHttpProxyBundle\Service;

use OpenAIBundle\Entity\ApiKey;

final class KeyPoolService
{
    private int $currentIndex = 0;

    /**
     * @var array<string, array<ApiKey>>
     */
    private array $keyCache = [];

    public function __construct(
        private readonly ApiKeyService $apiKeyService,
        private readonly string $strategy = 'round_robin',
    ) {
    }

    public function selectKey(string $model): ?ApiKey
    {
        $availableKeys = $this->getAvailableKeys($model);

        if ([] === $availableKeys) {
            return null;
        }

        return match ($this->strategy) {
            'round_robin' => $this->selectRoundRobin($availableKeys),
            'random' => $this->selectRandom($availableKeys),
            'least_used' => $this->selectLeastUsed($availableKeys),
            default => $this->selectRoundRobin($availableKeys),
        };
    }

    /**
     * @return array<ApiKey>
     */
    private function getAvailableKeys(string $model): array
    {
        $cacheKey = 'keys_' . $model;

        if (!isset($this->keyCache[$cacheKey])) {
            $this->keyCache[$cacheKey] = $this->apiKeyService->findAvailableForModel($model);
        }

        return $this->keyCache[$cacheKey];
    }

    /**
     * @param array<ApiKey> $keys
     */
    private function selectRoundRobin(array $keys): ApiKey
    {
        $key = $keys[$this->currentIndex % count($keys)];
        ++$this->currentIndex;

        return $key;
    }

    /**
     * @param array<ApiKey> $keys
     */
    private function selectRandom(array $keys): ApiKey
    {
        return $keys[array_rand($keys)];
    }

    /**
     * @param array<ApiKey> $keys
     */
    private function selectLeastUsed(array $keys): ApiKey
    {
        // 简单实现：返回第一个
        // 实际应该基于使用统计
        return $keys[0];
    }

    /**
     * @return array<string>
     */
    public function getAvailableModels(): array
    {
        $keys = $this->apiKeyService->findByStatus(true);
        $models = [];

        foreach ($keys as $key) {
            $keyModels = $this->getModelsForKey($key);
            $models = array_merge($models, $keyModels);
        }

        return array_unique($models);
    }

    /**
     * @return array<string>
     */
    private function getModelsForKey(ApiKey $key): array
    {
        // 从ApiKey获取支持的模型
        // 这里假设ApiKey有一个getModels方法或者配置
        return [
            'gpt-3.5-turbo',
            'gpt-4',
            'gpt-4-turbo',
            'text-embedding-ada-002',
        ];

        // 如果ApiKey有特定的模型配置，使用它
        // 否则返回默认模型
    }

    public function markKeyAsUsed(ApiKey $key): void
    {
        // 记录使用统计
        // 可以更新数据库或缓存
    }

    public function markKeyAsFailed(ApiKey $key, string $reason): void
    {
        // 标记Key失败
        // 可能需要暂时禁用或降低优先级
    }

    public function refreshCache(): void
    {
        $this->keyCache = [];
    }
}
