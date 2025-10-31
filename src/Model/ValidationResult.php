<?php

declare(strict_types=1);

namespace Tourze\OpenAiHttpProxyBundle\Model;

use Tourze\AccessKeyBundle\Entity\AccessKey;

final class ValidationResult
{
    public function __construct(
        private readonly bool $valid,
        private readonly ?string $error = null,
        private readonly ?AccessKey $caller = null,
    ) {
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getCaller(): ?AccessKey
    {
        return $this->caller;
    }
}
