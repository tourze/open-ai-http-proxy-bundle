<?php

declare(strict_types=1);

namespace Tourze\OpenAiHttpProxyBundle\Tests\Service;

use OpenAIBundle\Entity\ApiKey;
use Tourze\OpenAiHttpProxyBundle\Service\ApiKeyService;

class TestApiKeyService implements ApiKeyService
{
    /** @var ApiKey[] */
    private array $availableKeys = [];

    /** @var ApiKey[] */
    private array $statusKeys = [];

    /** @param ApiKey[] $keys */
    public function setAvailableKeys(array $keys): void
    {
        $this->availableKeys = $keys;
    }

    /** @param ApiKey[] $keys */
    public function setStatusKeys(array $keys): void
    {
        $this->statusKeys = $keys;
    }

    public function findAvailableForModel(string $model): array
    {
        return $this->availableKeys;
    }

    public function findByStatus(bool $status): array
    {
        return $this->statusKeys;
    }
}
