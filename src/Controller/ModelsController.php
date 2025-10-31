<?php

declare(strict_types=1);

namespace Tourze\OpenAiHttpProxyBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ModelsController extends AbstractProxyController
{
    #[Route(path: '/proxy/v1/models', name: 'proxy_models', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $tokenResult = $this->validateAndGetToken($request);
        if ($tokenResult instanceof Response) {
            return $tokenResult;
        }

        $poolStatus = $this->proxyService->getPoolStatus();
        $modelSet = $this->extractModelsFromClients($poolStatus['clients']);
        $models = $this->buildModelsArray($modelSet);

        return new JsonResponse([
            'object' => 'list',
            'data' => $models,
        ]);
    }

    /**
     * @param array<mixed> $clients
     * @return array<string, true>
     */
    private function extractModelsFromClients(array $clients): array
    {
        $modelSet = [];

        foreach ($clients as $client) {
            if (!is_array($client) || !isset($client['is_healthy']) || true !== $client['is_healthy']) {
                continue;
            }

            $clientModels = $this->parseClientModels($client['models'] ?? 'gpt-3.5-turbo,gpt-4');
            foreach ($clientModels as $model) {
                if (is_string($model)) {
                    $modelSet[trim($model)] = true;
                }
            }
        }

        return $modelSet;
    }

    /**
     * @param mixed $modelsValue
     * @return array<mixed>
     */
    private function parseClientModels(mixed $modelsValue): array
    {
        if (is_array($modelsValue)) {
            return $modelsValue;
        }

        if (is_string($modelsValue)) {
            return explode(',', $modelsValue);
        }

        return [];
    }

    /**
     * @param array<string, true> $modelSet
     * @return array<array<string, mixed>>
     */
    private function buildModelsArray(array $modelSet): array
    {
        $models = [];
        foreach (array_keys($modelSet) as $model) {
            $models[] = [
                'id' => $model,
                'object' => 'model',
                'created' => time(),
                'owned_by' => 'proxy',
            ];
        }

        return $models;
    }
}
