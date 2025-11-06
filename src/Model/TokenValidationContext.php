<?php

declare(strict_types=1);

namespace Tourze\OpenAiHttpProxyBundle\Model;

final class TokenValidationContext
{
    public function __construct(
        private readonly ?int $timestamp = null,
        private readonly ?string $nonce = null,
        private readonly ?string $userAgent = null,
        private readonly ?string $ipAddress = null,
    ) {
    }

    public function getTimestamp(): ?int
    {
        return $this->timestamp;
    }

    public function getNonce(): ?string
    {
        return $this->nonce;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function hasTimestamp(): bool
    {
        return $this->timestamp !== null;
    }

    public function isTimestampValid(int $allowedDrift = 300): bool
    {
        if (!$this->hasTimestamp()) {
            return false;
        }

        $now = time();
        $drift = abs($now - $this->timestamp);

        return $drift <= $allowedDrift;
    }
}