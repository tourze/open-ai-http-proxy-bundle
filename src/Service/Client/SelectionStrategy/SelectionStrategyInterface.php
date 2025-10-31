<?php

declare(strict_types=1);

namespace Tourze\OpenAiHttpProxyBundle\Service\Client\SelectionStrategy;

use Tourze\OpenAiContracts\Client\OpenAiCompatibleClientInterface;

interface SelectionStrategyInterface
{
    /**
     * @param array<int, array{client: OpenAiCompatibleClientInterface, provider: string, name: string, base_url: string, models: array<string>}> $candidates
     * @param array<string, mixed> $context
     */
    public function select(array $candidates, array $context = []): OpenAiCompatibleClientInterface;
}
