<?php

declare(strict_types=1);

namespace Tourze\OpenAiHttpProxyBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class StatusController extends AbstractProxyController
{
    #[Route(path: '/proxy/v1/status', name: 'proxy_status', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $tokenResult = $this->validateAndGetToken($request);
        if ($tokenResult instanceof Response) {
            return $tokenResult;
        }

        return new JsonResponse([
            'pool_status' => $this->proxyService->getPoolStatus(),
            'metrics' => $this->proxyService->getMetrics(),
        ]);
    }
}
