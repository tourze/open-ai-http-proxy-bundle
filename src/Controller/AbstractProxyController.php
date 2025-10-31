<?php

declare(strict_types=1);

namespace Tourze\OpenAiHttpProxyBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\Attribute\Required;
use Tourze\OpenAiHttpProxyBundle\Service\ProxyService;
use Tourze\OpenAiHttpProxyBundle\Service\TokenValidationService;

#[Exclude]
abstract class AbstractProxyController extends AbstractController
{
    protected TokenValidationService $tokenValidator;

    protected ProxyService $proxyService;

    #[Required]
    public function setTokenValidator(TokenValidationService $tokenValidator): void
    {
        $this->tokenValidator = $tokenValidator;
    }

    #[Required]
    public function setProxyService(ProxyService $proxyService): void
    {
        $this->proxyService = $proxyService;
    }

    protected function validateAndGetToken(Request $request): Response|string
    {
        $token = $this->validateToken($request);
        if (null === $token) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $validation = $this->tokenValidator->validate($token);
        if (!$validation->isValid()) {
            return new JsonResponse(['error' => $validation->getError()], Response::HTTP_UNAUTHORIZED);
        }

        return $token;
    }

    protected function validateToken(Request $request): ?string
    {
        $authHeader = $request->headers->get('Authorization');
        if (!is_string($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        return substr($authHeader, 7);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function parseJsonBody(Request $request): ?array
    {
        $content = $request->getContent();
        if ('' === $content) {
            return null;
        }

        $data = json_decode($content, true);

        if (!is_array($data)) {
            return null;
        }

        // Ensure all keys are strings (required for array<string, mixed>)
        /** @var array<string, mixed> $result */
        $result = [];
        foreach ($data as $key => $value) {
            if (!is_string($key)) {
                return null; // Invalid key type
            }
            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getProxyContext(Request $request): array
    {
        $strategy = $request->headers->get('X-Proxy-Strategy');
        $timeout = $request->headers->get('X-Proxy-Timeout');

        return [
            'strategy' => is_string($strategy) ? $strategy : 'weighted_score',
            'timeout' => is_numeric($timeout) ? (int) $timeout : 30,
        ];
    }

    protected function handleProxyError(\Exception $e): JsonResponse
    {
        return new JsonResponse(
            ['error' => 'Proxy error: ' . $e->getMessage()],
            Response::HTTP_INTERNAL_SERVER_ERROR
        );
    }
}
