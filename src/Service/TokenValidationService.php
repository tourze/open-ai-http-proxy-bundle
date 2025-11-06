<?php

declare(strict_types=1);

namespace Tourze\OpenAiHttpProxyBundle\Service;

use Psr\Log\LoggerInterface;
use Tourze\AccessKeyBundle\Entity\AccessKey;
use Tourze\OpenAiHttpProxyBundle\Model\TokenValidationContext;
use Tourze\OpenAiHttpProxyBundle\Model\ValidationResult;

final class TokenValidationService
{
    public function __construct(
        private readonly ApiCallerService $apiCallerService,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function canUseModel(string $token, string $model): bool
    {
        $validation = $this->validate($token);
        if (!$validation->isValid()) {
            return false;
        }

        $caller = $validation->getCaller();
        if (null === $caller) {
            return false;
        }

        // 检查是否有模型权限配置
        $allowedModels = $this->getAllowedModels($caller);

        if ([] === $allowedModels) {
            // 如果没有配置，允许所有（向后兼容）
            return true;
        }

        return in_array($model, $allowedModels, true);
    }

    public function validate(string $token, ?TokenValidationContext $context = null): ValidationResult
    {
        // 输入验证
        if (empty($token)) {
            $this->logSecurityEvent('empty_token', ['ip' => $context?->getIpAddress()]);
            return new ValidationResult(false, 'Token cannot be empty');
        }

        // 检查token格式 - 基础安全检查
        if (strlen($token) < 8) {
            $this->logSecurityEvent('token_too_short', ['ip' => $context?->getIpAddress()]);
            return new ValidationResult(false, 'Invalid token format');
        }

        // 查找有效的API调用者
        $caller = $this->apiCallerService->findValidApiCallerByToken($token);

        if (null === $caller) {
            $this->logSecurityEvent('invalid_token', ['token_prefix' => substr($token, 0, 8), 'ip' => $context?->getIpAddress()]);
            return new ValidationResult(false, 'Invalid token');
        }

        if (true !== $caller->isValid()) {
            $this->logSecurityEvent('disabled_token', ['caller_id' => $caller->getId(), 'ip' => $context?->getIpAddress()]);
            return new ValidationResult(false, 'Token is disabled');
        }

        // 检查签名超时（如果配置了）
        $signTimeout = $caller->getSignTimeoutSecond();
        if ($signTimeout > 0) {
            if (!$context || !$context->hasTimestamp()) {
                $this->logSecurityEvent('missing_timestamp', ['caller_id' => $caller->getId(), 'ip' => $context?->getIpAddress()]);
                return new ValidationResult(false, 'Timestamp required for this token');
            }

            // 允许一定的时钟漂移（最多10%的超时时间，最少5秒）
            $allowedDrift = max(5, intval($signTimeout * 0.1));

            if (!$context->isTimestampValid($signTimeout)) {
                $this->logSecurityEvent('timestamp_invalid', [
                    'caller_id' => $caller->getId(),
                    'timestamp' => $context->getTimestamp(),
                    'timeout' => $signTimeout,
                    'ip' => $context?->getIpAddress()
                ]);
                return new ValidationResult(false, 'Request timestamp is invalid or expired');
            }
        }

        return new ValidationResult(true, null, $caller);
    }

    /**
     * @return array<string>
     */
    private function getAllowedModels(AccessKey $caller): array
    {
        // 尝试从AccessKey的元数据中获取权限配置
        $metadata = $caller->getMetadata();
        if (isset($metadata['allowed_models']) && is_array($metadata['allowed_models'])) {
            return array_map('strval', $metadata['allowed_models']);
        }

        // 检查是否有自定义的权限字段
        if (method_exists($caller, 'getAllowedModels')) {
            $models = $caller->getAllowedModels();
            if (is_array($models)) {
                return array_map('strval', $models);
            }
        }

        // 向后兼容：返回空数组表示允许所有
        return [];
    }

    public function canAccessEndpoint(string $token, string $endpoint): bool
    {
        $validation = $this->validate($token);
        if (!$validation->isValid()) {
            return false;
        }

        $caller = $validation->getCaller();
        if (null === $caller) {
            return false;
        }

        // 检查端点权限
        $allowedEndpoints = $this->getAllowedEndpoints($caller);

        if ([] === $allowedEndpoints) {
            // 如果没有配置，允许所有（向后兼容）
            return true;
        }

        // 规范化端点路径
        $endpoint = '/' . ltrim($endpoint, '/');

        foreach ($allowedEndpoints as $allowed) {
            if ('*' === $allowed || $allowed === $endpoint) {
                return true;
            }

            // 支持通配符
            if (str_ends_with($allowed, '*')) {
                $prefix = rtrim($allowed, '*');
                if (str_starts_with($endpoint, $prefix)) {
                    return true;
                }
            }
        }

        $this->logSecurityEvent('endpoint_denied', [
            'caller_id' => $caller->getId(),
            'endpoint' => $endpoint,
            'allowed_endpoints' => $allowedEndpoints
        ]);

        return false;
    }

    /**
     * @return array<string>
     */
    private function getAllowedEndpoints(AccessKey $caller): array
    {
        // 尝试从AccessKey的元数据中获取权限配置
        $metadata = $caller->getMetadata();
        if (isset($metadata['allowed_endpoints']) && is_array($metadata['allowed_endpoints'])) {
            return array_map('strval', $metadata['allowed_endpoints']);
        }

        // 检查是否有自定义的权限字段
        if (method_exists($caller, 'getAllowedEndpoints')) {
            $endpoints = $caller->getAllowedEndpoints();
            if (is_array($endpoints)) {
                return array_map('strval', $endpoints);
            }
        }

        // 向后兼容：返回空数组表示允许所有
        return [];
    }

    public function checkRateLimit(string $token, ?TokenValidationContext $context = null): bool
    {
        $validation = $this->validate($token, $context);
        if (!$validation->isValid()) {
            return false;
        }

        $caller = $validation->getCaller();
        if (null === $caller) {
            return false;
        }

        // 简化实现：暂时不限制
        // TODO: 实现基于Redis或缓存的滑动窗口算法
        return true;
    }

    /**
     * 记录安全相关事件
     */
    private function logSecurityEvent(string $event, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->warning('Security event: ' . $event, $context);
        }
    }
}
