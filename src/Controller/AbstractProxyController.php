<?php

declare(strict_types=1);

namespace Tourze\OpenAiHttpProxyBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\Attribute\Required;
use Tourze\OpenAiHttpProxyBundle\Model\TokenValidationContext;
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
            return new JsonResponse(['error' => 'Unauthorized - Missing or invalid Authorization header'], Response::HTTP_UNAUTHORIZED);
        }

        $context = $this->createValidationContext($request);
        $validation = $this->tokenValidator->validate($token, $context);
        if (!$validation->isValid()) {
            return new JsonResponse(['error' => $validation->getError()], Response::HTTP_UNAUTHORIZED);
        }

        return $token;
    }

    protected function validateToken(Request $request): ?string
    {
        $authHeader = $request->headers->get('Authorization');

        // 严格验证Authorization header
        if (!is_string($authHeader)) {
            return null;
        }

        // 去除前后空白字符
        $authHeader = trim($authHeader);

        // 检查Bearer前缀（大小写不敏感）
        if (!preg_match('/^Bearer\s+/i', $authHeader)) {
            return null;
        }

        // 提取token部分
        $token = preg_replace('/^Bearer\s+/i', '', $authHeader);

        // 基础格式验证
        if (empty($token) || strlen($token) < 8) {
            return null;
        }

        // 检查token是否包含危险字符
        if (preg_match('/[\r\n\t]/', $token)) {
            return null;
        }

        return $token;
    }

    /**
     * 创建Token验证上下文
     */
    protected function createValidationContext(Request $request): TokenValidationContext
    {
        $timestamp = $this->extractTimestamp($request);
        $nonce = $request->headers->get('X-Request-Nonce');
        $userAgent = $request->headers->get('User-Agent');
        $ipAddress = $request->getClientIp();

        return new TokenValidationContext(
            timestamp: $timestamp,
            nonce: is_string($nonce) ? trim($nonce) : null,
            userAgent: is_string($userAgent) ? substr($userAgent, 0, 500) : null, // 限制长度
            ipAddress: $ipAddress
        );
    }

    /**
     * 从请求中提取时间戳
     */
    private function extractTimestamp(Request $request): ?int
    {
        // 优先使用X-Request-Timestamp头
        $timestampHeader = $request->headers->get('X-Request-Timestamp');
        if (is_string($timestampHeader)) {
            $timestamp = filter_var(trim($timestampHeader), FILTER_VALIDATE_INT);
            if ($timestamp !== false && $timestamp > 0) {
                return $timestamp;
            }
        }

        // 备选：使用Date头
        $dateHeader = $request->headers->get('Date');
        if (is_string($dateHeader)) {
            $date = \DateTime::createFromFormat(\DateTime::RFC7231, trim($dateHeader));
            if ($date !== false) {
                return $date->getTimestamp();
            }
        }

        return null;
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
