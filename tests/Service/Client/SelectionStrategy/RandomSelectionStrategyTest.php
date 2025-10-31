<?php

declare(strict_types=1);

namespace Tourze\OpenAiHttpProxyBundle\Tests\Service\Client\SelectionStrategy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\OpenAiHttpProxyBundle\Service\Client\SelectionStrategy\RandomSelectionStrategy;
use Tourze\OpenAiHttpProxyBundle\Tests\Service\Client\TestOpenAiCompatibleClient;

/**
 * @internal
 */
#[CoversClass(RandomSelectionStrategy::class)]
final class RandomSelectionStrategyTest extends TestCase
{
    private RandomSelectionStrategy $strategy;

    protected function setUp(): void
    {
        $this->strategy = new RandomSelectionStrategy();
    }

    public function testSelectFromSingleCandidate(): void
    {
        $client = new TestOpenAiCompatibleClient();
        $candidates = [
            1 => [
                'client' => $client,
                'provider' => 'TestProvider',
                'name' => 'test-client',
                'base_url' => 'https://api.test.com',
                'models' => ['gpt-3.5-turbo'],
            ],
        ];

        $selected = $this->strategy->select($candidates);

        $this->assertSame($client, $selected);
    }

    public function testSelectFromMultipleCandidates(): void
    {
        $client1 = new TestOpenAiCompatibleClient('test-client-1', 'https://api.test1.com');
        $client2 = new TestOpenAiCompatibleClient('test-client-2', 'https://api.test2.com');

        $candidates = [
            1 => [
                'client' => $client1,
                'provider' => 'TestProvider1',
                'name' => 'test-client-1',
                'base_url' => 'https://api.test1.com',
                'models' => ['gpt-3.5-turbo'],
            ],
            2 => [
                'client' => $client2,
                'provider' => 'TestProvider2',
                'name' => 'test-client-2',
                'base_url' => 'https://api.test2.com',
                'models' => ['gpt-4'],
            ],
        ];

        $selected = $this->strategy->select($candidates);

        $this->assertTrue($selected === $client1 || $selected === $client2);
    }

    public function testSelectWithContext(): void
    {
        $client = new TestOpenAiCompatibleClient();
        $candidates = [
            1 => [
                'client' => $client,
                'provider' => 'TestProvider',
                'name' => 'test-client',
                'base_url' => 'https://api.test.com',
                'models' => ['gpt-3.5-turbo'],
            ],
        ];

        $context = ['some' => 'context'];
        $selected = $this->strategy->select($candidates, $context);

        $this->assertSame($client, $selected);
    }
}
