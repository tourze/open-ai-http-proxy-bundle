<?php

declare(strict_types=1);

namespace Tourze\OpenAiHttpProxyBundle\Service;

use OpenAIBundle\Entity\ApiKey;

final class ModelMappingService
{
    /**
     * @var array<string, string>
     */
    private array $globalMappings;

    /**
     * @var array<string, array<string, string>>
     */
    private array $providerMappings;

    /**
     * @param array<string, mixed> $mappingConfig
     */
    public function __construct(array $mappingConfig = [])
    {
        $globalConfig = $mappingConfig['global'] ?? [];
        $this->globalMappings = $this->validateGlobalMappings($globalConfig);

        $providerConfig = $mappingConfig['providers'] ?? [];
        $this->providerMappings = $this->validateProviderMappings($providerConfig);
    }

    /**
     * @param mixed $config
     * @return array<string, string>
     */
    private function validateGlobalMappings($config): array
    {
        if (!is_array($config)) {
            return [];
        }

        $result = [];
        foreach ($config as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * @param mixed $config
     * @return array<string, array<string, string>>
     */
    private function validateProviderMappings($config): array
    {
        if (!is_array($config)) {
            return [];
        }

        $result = [];
        foreach ($config as $provider => $mappings) {
            if (is_string($provider) && is_array($mappings)) {
                $result[$provider] = $this->validateGlobalMappings($mappings);
            }
        }

        return $result;
    }

    public function map(string $model, ApiKey $apiKey): string
    {
        // 1. 先检查针对特定Provider的映射
        $provider = $this->getProviderFromApiKey($apiKey);
        if (null !== $provider && isset($this->providerMappings[$provider][$model])) {
            return $this->providerMappings[$provider][$model];
        }

        // 2. 检查全局映射
        if (isset($this->globalMappings[$model])) {
            return $this->globalMappings[$model];
        }

        // 3. 如果没有映射，返回原始模型名
        return $model;
    }

    private function getProviderFromApiKey(ApiKey $apiKey): ?string
    {
        // 从ApiKey的chatCompletionUrl判断provider
        $chatCompletionUrl = $apiKey->getChatCompletionUrl();

        if (str_contains($chatCompletionUrl, 'openai.com')) {
            return 'openai';
        }

        if (str_contains($chatCompletionUrl, 'azure.com')) {
            return 'azure';
        }

        if (str_contains($chatCompletionUrl, 'anthropic.com')) {
            return 'anthropic';
        }

        // 可以从ApiKey的额外配置中获取
        return null;
    }

    public function setGlobalMapping(string $source, string $target): void
    {
        $this->globalMappings[$source] = $target;
    }

    public function setProviderMapping(string $provider, string $source, string $target): void
    {
        if (!isset($this->providerMappings[$provider])) {
            $this->providerMappings[$provider] = [];
        }
        $this->providerMappings[$provider][$source] = $target;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefaultMappings(): array
    {
        return [
            'global' => [
                // OpenAI标准映射
                'gpt-4' => 'gpt-4-0613',
                'gpt-3.5-turbo' => 'gpt-3.5-turbo-0613',
            ],
            'providers' => [
                'azure' => [
                    // Azure需要deployment名称
                    'gpt-4' => 'gpt-4-deployment',
                    'gpt-3.5-turbo' => 'gpt-35-turbo-deployment',
                ],
                'anthropic' => [
                    // Claude映射
                    'gpt-4' => 'claude-3-opus-20240229',
                    'gpt-3.5-turbo' => 'claude-3-sonnet-20240229',
                ],
            ],
        ];
    }
}
