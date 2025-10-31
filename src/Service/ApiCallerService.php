<?php

declare(strict_types=1);

namespace Tourze\OpenAiHttpProxyBundle\Service;

use Tourze\AccessKeyBundle\Entity\AccessKey;

interface ApiCallerService
{
    public function findValidApiCallerByToken(string $token): ?AccessKey;
}
