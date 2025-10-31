<?php

declare(strict_types=1);

namespace Tourze\OpenAiHttpProxyBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CompletionsController extends AbstractProxyController
{
    #[Route(path: '/proxy/v1/completions', name: 'proxy_completions', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $tokenResult = $this->validateAndGetToken($request);
        if ($tokenResult instanceof Response) {
            return $tokenResult;
        }
        $token = $tokenResult;

        $data = $this->parseJsonBody($request);
        if (null === $data) {
            return new JsonResponse(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        $model = $data['model'] ?? 'gpt-3.5-turbo';

        if (!$this->tokenValidator->canAccessEndpoint($token, '/completions')) {
            return new JsonResponse(['error' => 'Endpoint not allowed'], Response::HTTP_FORBIDDEN);
        }

        $context = $this->getProxyContext($request);

        try {
            return $this->proxyService->forward('/completions', $data, $context);
        } catch (\Exception $e) {
            return $this->handleProxyError($e);
        }
    }
}
