<?php

declare(strict_types=1);

namespace Tourze\OpenAiHttpProxyBundle\Tests\Service\Client;

use Tourze\OpenAiContracts\Client\OpenAiCompatibleClientInterface;
use Tourze\OpenAiContracts\Request\ChatCompletionRequestInterface;
use Tourze\OpenAiContracts\Response\BalanceResponseInterface;
use Tourze\OpenAiContracts\Response\ChatCompletionResponseInterface;
use Tourze\OpenAiContracts\Response\ModelListResponseInterface;

class TestOpenAiCompatibleClientWithBalance implements OpenAiCompatibleClientInterface
{
    private readonly TestOpenAiCompatibleClient $baseClient;

    public function __construct(
        private readonly BalanceResponseInterface $balanceResponse,
        string $name = 'test-client',
        string $baseUrl = 'https://api.test.com',
        bool $available = true,
        ?string $lastError = null,
        ?string $apiKey = null,
    ) {
        $this->baseClient = new TestOpenAiCompatibleClient($name, $baseUrl, $available, $lastError, $apiKey);
    }

    public function chatCompletion(ChatCompletionRequestInterface $request): ChatCompletionResponseInterface
    {
        return $this->baseClient->chatCompletion($request);
    }

    public function listModels(): ModelListResponseInterface
    {
        return $this->baseClient->listModels();
    }

    public function getBalance(): BalanceResponseInterface
    {
        return $this->balanceResponse;
    }

    public function setApiKey(string $apiKey): void
    {
        $this->baseClient->setApiKey($apiKey);
    }

    public function getApiKey(): ?string
    {
        return $this->baseClient->getApiKey();
    }

    public function getName(): string
    {
        return $this->baseClient->getName();
    }

    public function getBaseUrl(): string
    {
        return $this->baseClient->getBaseUrl();
    }

    public function isAvailable(): bool
    {
        return $this->baseClient->isAvailable();
    }

    public function getLastError(): ?string
    {
        return $this->baseClient->getLastError();
    }
}
