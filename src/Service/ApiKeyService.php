<?php

declare(strict_types=1);

namespace Tourze\OpenAiHttpProxyBundle\Service;

use OpenAIBundle\Entity\ApiKey;

interface ApiKeyService
{
    /**
     * @return ApiKey[]
     */
    public function findAvailableForModel(string $model): array;

    /**
     * @return ApiKey[]
     */
    public function findByStatus(bool $status): array;
}
