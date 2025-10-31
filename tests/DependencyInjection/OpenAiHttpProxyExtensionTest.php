<?php

declare(strict_types=1);

namespace Tourze\OpenAiHttpProxyBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\OpenAiHttpProxyBundle\DependencyInjection\OpenAiHttpProxyExtension;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;

/**
 * @internal
 */
#[CoversClass(OpenAiHttpProxyExtension::class)]
final class OpenAiHttpProxyExtensionTest extends AbstractDependencyInjectionExtensionTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testLoadWithDefaultConfig(): void
    {
        $extension = new OpenAiHttpProxyExtension();
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');

        $configs = [[]];
        $extension->load($configs, $container);

        // 验证服务是否已注册
        $this->assertTrue($container->hasDefinition('Tourze\OpenAiHttpProxyBundle\Service\ProxyService'));
        $this->assertTrue($container->hasDefinition('Tourze\OpenAiHttpProxyBundle\Service\TokenValidationService'));
        $this->assertTrue($container->hasDefinition('Tourze\OpenAiHttpProxyBundle\Service\ClientSelectorService'));
    }

    public function testLoadWithCustomConfig(): void
    {
        $extension = new OpenAiHttpProxyExtension();
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');

        $configs = [[
            'model_mappings' => [
                'global' => [
                    'gpt-4' => 'gpt-4-turbo',
                ],
                'providers' => [
                    'azure' => [
                        'gpt-3.5-turbo' => 'gpt-35-turbo',
                    ],
                ],
            ],
        ]];

        $extension->load($configs, $container);

        // 由于 Extension 只是简单加载 services.yaml，不处理配置参数
        // 只验证基本服务是否注册
        $this->assertTrue($container->hasDefinition('Tourze\OpenAiHttpProxyBundle\Service\ProxyService'));
        $this->assertTrue($container->hasDefinition('Tourze\OpenAiHttpProxyBundle\Service\TokenValidationService'));
    }

    public function testGetAlias(): void
    {
        $extension = new OpenAiHttpProxyExtension();
        $this->assertEquals('open_ai_http_proxy', $extension->getAlias());
    }
}
