<?php

declare(strict_types=1);

namespace Tourze\OpenAiHttpProxyBundle\Tests\Service;

use OpenAIBundle\Entity\ApiKey;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\OpenAiHttpProxyBundle\Service\ModelMappingService;

/**
 * @internal
 */
#[CoversClass(ModelMappingService::class)]
final class ModelMappingServiceTest extends TestCase
{
    public function testGlobalMapping(): void
    {
        $config = [
            'global' => [
                'gpt-4' => 'gpt-4-0613',
                'gpt-3.5-turbo' => 'gpt-3.5-turbo-0613',
            ],
            'providers' => [],
        ];

        $service = new ModelMappingService($config);

        $apiKey = new class extends ApiKey {
            public function __construct()
            {
                parent::__construct();
            }

            public function getChatCompletionUrl(): string
            {
                return 'https://api.openai.com/v1/chat/completions';
            }
        };

        $this->assertEquals('gpt-4-0613', $service->map('gpt-4', $apiKey));
        $this->assertEquals('gpt-3.5-turbo-0613', $service->map('gpt-3.5-turbo', $apiKey));
        $this->assertEquals('unknown-model', $service->map('unknown-model', $apiKey));
    }

    public function testProviderSpecificMapping(): void
    {
        $config = [
            'global' => [
                'gpt-4' => 'gpt-4-0613',
            ],
            'providers' => [
                'azure' => [
                    'gpt-4' => 'gpt-4-deployment',
                ],
            ],
        ];

        $service = new ModelMappingService($config);

        // OpenAI key - should use global mapping
        $openaiKey = new class extends ApiKey {
            public function __construct()
            {
                parent::__construct();
            }

            public function getChatCompletionUrl(): string
            {
                return 'https://api.openai.com/v1/chat/completions';
            }
        };
        $this->assertEquals('gpt-4-0613', $service->map('gpt-4', $openaiKey));

        // Azure key - should use provider-specific mapping
        $azureKey = new class extends ApiKey {
            public function __construct()
            {
                parent::__construct();
            }

            public function getChatCompletionUrl(): string
            {
                return 'https://test.openai.azure.com/openai/deployments/gpt-4/chat/completions';
            }
        };
        $this->assertEquals('gpt-4-deployment', $service->map('gpt-4', $azureKey));
    }

    public function testSetGlobalMapping(): void
    {
        $service = new ModelMappingService();
        $service->setGlobalMapping('custom-model', 'mapped-model');

        $apiKey = new class extends ApiKey {
            public function __construct()
            {
                parent::__construct();
            }

            public function getChatCompletionUrl(): string
            {
                return 'https://api.openai.com/v1/chat/completions';
            }
        };

        $this->assertEquals('mapped-model', $service->map('custom-model', $apiKey));
    }

    public function testSetProviderMapping(): void
    {
        $service = new ModelMappingService();
        $service->setProviderMapping('openai', 'test-model', 'openai-test');

        $apiKey = new class extends ApiKey {
            public function __construct()
            {
                parent::__construct();
            }

            public function getChatCompletionUrl(): string
            {
                return 'https://api.openai.com/v1/chat/completions';
            }
        };

        $this->assertEquals('openai-test', $service->map('test-model', $apiKey));
    }

    public function testMap(): void
    {
        // Test basic map functionality
        $service = new ModelMappingService();

        $apiKey = new class extends ApiKey {
            public function __construct()
            {
                parent::__construct();
            }

            public function getChatCompletionUrl(): string
            {
                return 'https://api.openai.com/v1/chat/completions';
            }
        };

        // Test unmapped model returns original
        $this->assertEquals('test-model', $service->map('test-model', $apiKey));

        // Test with anthropic provider
        $anthropicKey = new class extends ApiKey {
            public function __construct()
            {
                parent::__construct();
            }

            public function getChatCompletionUrl(): string
            {
                return 'https://api.anthropic.com/v1/messages';
            }
        };
        $this->assertEquals('test-model', $service->map('test-model', $anthropicKey));

        // Test with default mappings
        $defaultService = new ModelMappingService($service->getDefaultMappings());
        $this->assertEquals('gpt-4-0613', $defaultService->map('gpt-4', $apiKey));
    }
}
