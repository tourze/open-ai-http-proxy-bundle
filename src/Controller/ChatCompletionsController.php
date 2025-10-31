<?php

declare(strict_types=1);

namespace Tourze\OpenAiHttpProxyBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ChatCompletionsController extends AbstractProxyController
{
    #[Route(path: '/proxy/v1/chat/completions', name: 'proxy_chat_completions', methods: ['POST'])]
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

        $modelValue = $data['model'] ?? 'gpt-3.5-turbo';
        $model = is_string($modelValue) ? $modelValue : 'gpt-3.5-turbo';
        $stream = (bool) ($data['stream'] ?? false);

        if (!$this->tokenValidator->canUseModel($token, $model)) {
            return new JsonResponse(['error' => 'Model not allowed'], Response::HTTP_FORBIDDEN);
        }

        $context = $this->getProxyContext($request);

        try {
            if ($stream) {
                return $this->proxyService->forwardStream('/chat/completions', $data, $context);
            }

            return $this->proxyService->forward('/chat/completions', $data, $context);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'error' => [
                    'message' => 'Proxy error',
                    'type' => 'proxy_error',
                    'details' => $e->getMessage(),
                ],
            ], 500);
        }
    }
}
