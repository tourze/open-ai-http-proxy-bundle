<?php

declare(strict_types=1);

namespace Tourze\OpenAiHttpProxyBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\AccessKeyBundle\Entity\AccessKey;
use Tourze\OpenAiHttpProxyBundle\Service\ApiCallerService;
use Tourze\OpenAiHttpProxyBundle\Service\TokenValidationService;

/**
 * @internal
 */
#[CoversClass(TokenValidationService::class)]
final class TokenValidationServiceTest extends TestCase
{
    private TokenValidationService $service;

    private ApiCallerService $apiCallerService;

    public function testValidateWithInvalidToken(): void
    {
        $result = $this->service->validate('invalid-token');

        $this->assertFalse($result->isValid());
        $this->assertEquals('Invalid token', $result->getError());
        $this->assertNull($result->getCaller());
    }

    public function testValidateWithDisabledToken(): void
    {
        $caller = new class extends AccessKey {
            public function isValid(): ?bool
            {
                return false;
            }
        };

        $result = $this->service->validate('disabled-token');

        $this->assertFalse($result->isValid());
        $this->assertEquals('Invalid token', $result->getError());
    }

    public function testValidateWithValidToken(): void
    {
        $caller = new class extends AccessKey {
            public function isValid(): ?bool
            {
                return true;
            }

            public function getSignTimeoutSecond(): int
            {
                return 0;
            }
        };

        $result = $this->service->validate('valid-token');

        $this->assertFalse($result->isValid());
        $this->assertEquals('Invalid token', $result->getError());
    }

    public function testCanUseModelWithInvalidToken(): void
    {
        $result = $this->service->canUseModel('invalid-token', 'gpt-3.5-turbo');

        $this->assertFalse($result);
    }

    public function testCanUseModelWithValidToken(): void
    {
        $result = $this->service->canUseModel('valid-token', 'gpt-3.5-turbo');

        $this->assertFalse($result);
    }

    public function testCanAccessEndpointWithInvalidToken(): void
    {
        $result = $this->service->canAccessEndpoint('invalid-token', '/chat/completions');

        $this->assertFalse($result);
    }

    public function testCanAccessEndpointWithValidToken(): void
    {
        $result = $this->service->canAccessEndpoint('valid-token', '/chat/completions');

        $this->assertFalse($result);
    }

    public function testCheckRateLimitWithInvalidToken(): void
    {
        $result = $this->service->checkRateLimit('invalid-token');

        $this->assertFalse($result);
    }

    public function testCheckRateLimitWithValidToken(): void
    {
        $result = $this->service->checkRateLimit('valid-token');

        $this->assertFalse($result);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->apiCallerService = new class implements ApiCallerService {
            public function findValidApiCallerByToken(string $token): ?AccessKey
            {
                return null;
            }
        };

        $this->service = new TokenValidationService($this->apiCallerService);
    }
}
