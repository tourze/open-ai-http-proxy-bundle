<?php

declare(strict_types=1);

namespace Tourze\OpenAiHttpProxyBundle\Tests\Service\Client;

use Tourze\OpenAiContracts\Client\OpenAiCompatibleClientInterface;
use Tourze\OpenAiContracts\Request\ChatCompletionRequestInterface;
use Tourze\OpenAiContracts\Response\BalanceResponseInterface;
use Tourze\OpenAiContracts\Response\ChatCompletionResponseInterface;
use Tourze\OpenAiContracts\Response\ModelListResponseInterface;

class TestOpenAiCompatibleClient implements OpenAiCompatibleClientInterface
{
    public function __construct(
        private readonly string $name = 'test-client',
        private readonly string $baseUrl = 'https://api.test.com',
        private readonly bool $available = true,
        private readonly ?string $lastError = null,
        private readonly ?string $apiKey = null,
    ) {
    }

    public function chatCompletion(ChatCompletionRequestInterface $request): ChatCompletionResponseInterface
    {
        throw new \BadMethodCallException('Not implemented');
    }

    public function listModels(): ModelListResponseInterface
    {
        throw new \BadMethodCallException('Not implemented');
    }

    public function getBalance(): BalanceResponseInterface
    {
        throw new \BadMethodCallException('Not implemented');
    }

    public function setApiKey(string $apiKey): void
    {
        // Test implementation - do nothing
    }

    public function getApiKey(): ?string
    {
        return $this->apiKey;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }
}
