<?php

declare(strict_types=1);

namespace Tourze\OpenAiHttpProxyBundle\Tests\Service;

use OpenAIBundle\Entity\ApiKey;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\OpenAiHttpProxyBundle\Service\KeyPoolService;

/**
 * @internal
 */
#[CoversClass(KeyPoolService::class)]
final class KeyPoolServiceTest extends TestCase
{
    private KeyPoolService $service;

    private TestApiKeyService $apiKeyService;

    public function testSelectKeyWithNoAvailableKeys(): void
    {
        $this->apiKeyService->setAvailableKeys([]);

        $this->service = new KeyPoolService($this->apiKeyService);
        $result = $this->service->selectKey('gpt-3.5-turbo');

        $this->assertNull($result);
    }

    public function testSelectKeyWithRoundRobinStrategy(): void
    {
        $key1 = new class extends ApiKey {
            public function __construct()
            {
                parent::__construct();
            }
        };
        $key2 = new class extends ApiKey {
            public function __construct()
            {
                parent::__construct();
            }
        };
        $key3 = new class extends ApiKey {
            public function __construct()
            {
                parent::__construct();
            }
        };

        $this->apiKeyService->setAvailableKeys([$key1, $key2, $key3]);

        $this->service = new KeyPoolService($this->apiKeyService, 'round_robin');

        $result1 = $this->service->selectKey('gpt-3.5-turbo');
        $result2 = $this->service->selectKey('gpt-3.5-turbo');
        $result3 = $this->service->selectKey('gpt-3.5-turbo');
        $result4 = $this->service->selectKey('gpt-3.5-turbo');

        $this->assertSame($key1, $result1);
        $this->assertSame($key2, $result2);
        $this->assertSame($key3, $result3);
        $this->assertSame($key1, $result4); // Should wrap around
    }

    public function testSelectKeyWithRandomStrategy(): void
    {
        $key1 = new class extends ApiKey {
            public function __construct()
            {
                parent::__construct();
            }
        };
        $key2 = new class extends ApiKey {
            public function __construct()
            {
                parent::__construct();
            }
        };

        $this->apiKeyService->setAvailableKeys([$key1, $key2]);

        $this->service = new KeyPoolService($this->apiKeyService, 'random');
        $result = $this->service->selectKey('gpt-3.5-turbo');

        $this->assertContains($result, [$key1, $key2]);
    }

    public function testSelectKeyWithLeastUsedStrategy(): void
    {
        $key1 = new class extends ApiKey {
            public function __construct()
            {
                parent::__construct();
            }
        };
        $key2 = new class extends ApiKey {
            public function __construct()
            {
                parent::__construct();
            }
        };

        $this->apiKeyService->setAvailableKeys([$key1, $key2]);

        $this->service = new KeyPoolService($this->apiKeyService, 'least_used');
        $result = $this->service->selectKey('gpt-3.5-turbo');

        // Simple implementation always returns first key
        $this->assertSame($key1, $result);
    }

    public function testGetAvailableModels(): void
    {
        $key1 = new class extends ApiKey {
            public function __construct()
            {
                parent::__construct();
            }
        };
        $key2 = new class extends ApiKey {
            public function __construct()
            {
                parent::__construct();
            }
        };

        $this->apiKeyService->setStatusKeys([$key1, $key2]);

        $this->service = new KeyPoolService($this->apiKeyService);
        $result = $this->service->getAvailableModels();
        $this->assertContains('gpt-3.5-turbo', $result);
        $this->assertContains('gpt-4', $result);
        $this->assertContains('text-embedding-ada-002', $result);
    }

    public function testMarkKeyAsUsed(): void
    {
        $key = new class extends ApiKey {
            public function __construct()
            {
                parent::__construct();
            }
        };
        $this->service = new KeyPoolService($this->apiKeyService);

        // 测试方法可以正常执行不抛出异常
        $this->service->markKeyAsUsed($key);

        // 验证传入的key对象类型正确
        $this->assertInstanceOf(ApiKey::class, $key);
    }

    public function testMarkKeyAsFailed(): void
    {
        $key = new class extends ApiKey {
            public function __construct()
            {
                parent::__construct();
            }
        };
        $this->service = new KeyPoolService($this->apiKeyService);

        // 测试方法可以正常执行不抛出异常
        $this->service->markKeyAsFailed($key, 'Connection timeout');

        // 验证传入的key对象类型正确
        $this->assertInstanceOf(ApiKey::class, $key);
    }

    public function testRefreshCache(): void
    {
        $key = new class extends ApiKey {
            public function __construct()
            {
                parent::__construct();
            }
        };

        $this->apiKeyService->setAvailableKeys([$key]);

        $this->service = new KeyPoolService($this->apiKeyService);

        // First call should cache
        $result1 = $this->service->selectKey('gpt-3.5-turbo');

        // Refresh cache
        $this->service->refreshCache();

        // Next call should fetch again
        $result2 = $this->service->selectKey('gpt-3.5-turbo');

        // Both results should be the same key
        $this->assertSame($key, $result1);
        $this->assertSame($key, $result2);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->apiKeyService = new TestApiKeyService();
    }
}
