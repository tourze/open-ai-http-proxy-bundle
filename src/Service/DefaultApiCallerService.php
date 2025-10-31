<?php

declare(strict_types=1);

namespace Tourze\OpenAiHttpProxyBundle\Service;

use Tourze\AccessKeyBundle\Entity\AccessKey;
use Tourze\AccessKeyBundle\Service\ApiCallerService as AccessKeyApiCallerService;

final class DefaultApiCallerService implements ApiCallerService
{
    public function __construct(
        private readonly AccessKeyApiCallerService $accessKeyService,
    ) {
    }

    public function findValidApiCallerByToken(string $token): ?AccessKey
    {
        return $this->accessKeyService->findValidApiCallerByAppId($token);
    }
}
