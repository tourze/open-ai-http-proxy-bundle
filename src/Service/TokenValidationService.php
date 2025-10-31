<?php

declare(strict_types=1);

namespace Tourze\OpenAiHttpProxyBundle\Service;

use Tourze\AccessKeyBundle\Entity\AccessKey;
use Tourze\OpenAiHttpProxyBundle\Model\ValidationResult;

final class TokenValidationService
{
    public function __construct(
        private readonly ApiCallerService $apiCallerService,
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
        // 这里可以从ApiCaller的元数据或配置中获取
        // 简化实现：允许所有模型
        $allowedModels = $this->getAllowedModels($caller);

        if ([] === $allowedModels) {
            // 如果没有配置，允许所有
            return true;
        }

        return in_array($model, $allowedModels, true);
    }

    public function validate(string $token): ValidationResult
    {
        // 检查缓存
        $caller = $this->apiCallerService->findValidApiCallerByToken($token);

        if (null === $caller) {
            return new ValidationResult(false, 'Invalid token');
        }

        if (true !== $caller->isValid()) {
            return new ValidationResult(false, 'Token is disabled');
        }

        // 检查签名超时（如果配置了）
        $signTimeout = $caller->getSignTimeoutSecond();
        if ($signTimeout > 0) {
            // 这里应该检查请求时间戳
            // 简化实现，暂时跳过
        }

        return new ValidationResult(true, null, $caller);
    }

    /**
     * @return array<string>
     */
    private function getAllowedModels(AccessKey $caller): array
    {
        // 从ApiCaller的配置或元数据中获取
        // 这里可以扩展ApiCaller实体，添加权限字段
        // 或者使用其他方式存储权限配置

        // 简化实现：返回空数组表示允许所有
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
            // 如果没有配置，允许所有
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

        return false;
    }

    /**
     * @return array<string>
     */
    private function getAllowedEndpoints(AccessKey $caller): array
    {
        // 类似getAllowedModels
        // 简化实现：返回空数组表示允许所有
        return [];
    }

    public function checkRateLimit(string $token): bool
    {
        $validation = $this->validate($token);
        if (!$validation->isValid()) {
            return false;
        }

        // 简化实现：暂时不限制
        // 实际应该使用Redis或其他缓存实现滑动窗口算法
        return true;
    }
}
