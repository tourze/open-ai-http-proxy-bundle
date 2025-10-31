<?php

namespace Tourze\OpenAiHttpProxyBundle;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\AccessKeyBundle\AccessKeyBundle;
use Tourze\BundleDependency\BundleDependencyInterface;
use Tourze\HttpForwardBundle\HttpForwardBundle;
use Tourze\RoutingAutoLoaderBundle\RoutingAutoLoaderBundle;

class OpenAiHttpProxyBundle extends Bundle implements BundleDependencyInterface
{
    public static function getBundleDependencies(): array
    {
        return [
            DoctrineBundle::class => ['all' => true],
            RoutingAutoLoaderBundle::class => ['all' => true],
            HttpForwardBundle::class => ['all' => true],
            AccessKeyBundle::class => ['all' => true],
        ];
    }
}
