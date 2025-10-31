<?php

declare(strict_types=1);

namespace Tourze\OpenAiHttpProxyBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\OpenAiHttpProxyBundle\OpenAiHttpProxyBundle;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;

/**
 * @internal
 */
#[CoversClass(OpenAiHttpProxyBundle::class)]
#[RunTestsInSeparateProcesses]
final class OpenAiHttpProxyBundleTest extends AbstractBundleTestCase
{
}
