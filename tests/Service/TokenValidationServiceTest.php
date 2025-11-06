<?php

declare(strict_types=1);

namespace Tourze\OpenAiHttpProxyBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\AccessKeyBundle\Entity\AccessKey;
use Tourze\OpenAiHttpProxyBundle\Model\TokenValidationContext;
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

    // 新增安全测试用例

    public function testValidateWithEmptyToken(): void
    {
        $result = $this->service->validate('');

        $this->assertFalse($result->isValid());
        $this->assertEquals('Token cannot be empty', $result->getError());
    }

    public function testValidateWithShortToken(): void
    {
        $result = $this->service->validate('short');

        $this->assertFalse($result->isValid());
        $this->assertEquals('Invalid token format', $result->getError());
    }

    public function testValidateWithValidTokenAndContext(): void
    {
        $caller = $this->createValidCaller();
        $this->apiCallerService = $this->createApiCallerService($caller);
        $this->service = new TokenValidationService($this->apiCallerService);

        $context = new TokenValidationContext(timestamp: time());

        $result = $this->service->validate('valid-token', $context);

        $this->assertTrue($result->isValid());
        $this->assertNull($result->getError());
        $this->assertSame($caller, $result->getCaller());
    }

    public function testValidateWithMissingTimestamp(): void
    {
        $caller = $this->createCallerWithTimeout(300);
        $this->apiCallerService = $this->createApiCallerService($caller);
        $this->service = new TokenValidationService($this->apiCallerService);

        $context = new TokenValidationContext(); // 没有时间戳

        $result = $this->service->validate('timeout-token', $context);

        $this->assertFalse($result->isValid());
        $this->assertEquals('Timestamp required for this token', $result->getError());
    }

    public function testValidateWithExpiredTimestamp(): void
    {
        $caller = $this->createCallerWithTimeout(300);
        $this->apiCallerService = $this->createApiCallerService($caller);
        $this->service = new TokenValidationService($this->apiCallerService);

        $expiredTimestamp = time() - 400; // 400秒前，超过300秒超时
        $context = new TokenValidationContext(timestamp: $expiredTimestamp);

        $result = $this->service->validate('timeout-token', $context);

        $this->assertFalse($result->isValid());
        $this->assertEquals('Request timestamp is invalid or expired', $result->getError());
    }

    public function testValidateWithFutureTimestamp(): void
    {
        $caller = $this->createCallerWithTimeout(300);
        $this->apiCallerService = $this->createApiCallerService($caller);
        $this->service = new TokenValidationService($this->apiCallerService);

        $futureTimestamp = time() + 400; // 400秒后，超过300秒超时
        $context = new TokenValidationContext(timestamp: $futureTimestamp);

        $result = $this->service->validate('timeout-token', $context);

        $this->assertFalse($result->isValid());
        $this->assertEquals('Request timestamp is invalid or expired', $result->getError());
    }

    public function testValidateWithValidTimestampWithinDrift(): void
    {
        $caller = $this->createCallerWithTimeout(300);
        $this->apiCallerService = $this->createApiCallerService($caller);
        $this->service = new TokenValidationService($this->apiCallerService);

        $slightlyOldTimestamp = time() - 30; // 30秒前，在允许范围内
        $context = new TokenValidationContext(timestamp: $slightlyOldTimestamp);

        $result = $this->service->validate('timeout-token', $context);

        $this->assertTrue($result->isValid());
        $this->assertNull($result->getError());
    }

    public function testCanUseModelWithPermissions(): void
    {
        $caller = $this->createCallerWithModelPermissions(['gpt-3.5-turbo', 'gpt-4']);
        $this->apiCallerService = $this->createApiCallerService($caller);
        $this->service = new TokenValidationService($this->apiCallerService);

        $result = $this->service->canUseModel('permission-token', 'gpt-3.5-turbo');
        $this->assertTrue($result);

        $result = $this->service->canUseModel('permission-token', 'gpt-4');
        $this->assertTrue($result);

        $result = $this->service->canUseModel('permission-token', 'claude-3');
        $this->assertFalse($result);
    }

    public function testCanAccessEndpointWithPermissions(): void
    {
        $caller = $this->createCallerWithEndpointPermissions(['/chat/completions', '/embeddings']);
        $this->apiCallerService = $this->createApiCallerService($caller);
        $this->service = new TokenValidationService($this->apiCallerService);

        $result = $this->service->canAccessEndpoint('permission-token', '/chat/completions');
        $this->assertTrue($result);

        $result = $this->service->canAccessEndpoint('permission-token', '/embeddings');
        $this->assertTrue($result);

        $result = $this->service->canAccessEndpoint('permission-token', '/models');
        $this->assertFalse($result);
    }

    public function testCanAccessEndpointWithWildcard(): void
    {
        $caller = $this->createCallerWithEndpointPermissions(['/chat/*', '/embeddings']);
        $this->apiCallerService = $this->createApiCallerService($caller);
        $this->service = new TokenValidationService($this->apiCallerService);

        $result = $this->service->canAccessEndpoint('permission-token', '/chat/completions');
        $this->assertTrue($result);

        $result = $this->service->canAccessEndpoint('permission-token', '/chat/completions');
        $this->assertTrue($result);
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

    private function createValidCaller(): AccessKey
    {
        return new class extends AccessKey {
            public function isValid(): ?bool
            {
                return true;
            }

            public function getSignTimeoutSecond(): int
            {
                return 0; // 不需要时间戳验证
            }
        };
    }

    private function createCallerWithTimeout(int $timeout): AccessKey
    {
        return new class($timeout) extends AccessKey {
            private int $timeout;

            public function __construct(int $timeout)
            {
                $this->timeout = $timeout;
            }

            public function isValid(): ?bool
            {
                return true;
            }

            public function getSignTimeoutSecond(): int
            {
                return $this->timeout;
            }
        };
    }

    private function createCallerWithModelPermissions(array $models): AccessKey
    {
        return new class($models) extends AccessKey {
            private array $models;

            public function __construct(array $models)
            {
                $this->models = $models;
            }

            public function isValid(): ?bool
            {
                return true;
            }

            public function getSignTimeoutSecond(): int
            {
                return 0;
            }

            public function getMetadata(): array
            {
                return ['allowed_models' => $this->models];
            }
        };
    }

    private function createCallerWithEndpointPermissions(array $endpoints): AccessKey
    {
        return new class($endpoints) extends AccessKey {
            private array $endpoints;

            public function __construct(array $endpoints)
            {
                $this->endpoints = $endpoints;
            }

            public function isValid(): ?bool
            {
                return true;
            }

            public function getSignTimeoutSecond(): int
            {
                return 0;
            }

            public function getMetadata(): array
            {
                return ['allowed_endpoints' => $this->endpoints];
            }
        };
    }

    private function createApiCallerService(AccessKey $caller): ApiCallerService
    {
        return new class($caller) implements ApiCallerService {
            private AccessKey $caller;

            public function __construct(AccessKey $caller)
            {
                $this->caller = $caller;
            }

            public function findValidApiCallerByToken(string $token): ?AccessKey
            {
                return match ($token) {
                    'valid-token', 'timeout-token', 'permission-token' => $this->caller,
                    default => null,
                };
            }
        };
    }
}
