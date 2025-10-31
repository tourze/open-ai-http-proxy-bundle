<?php

declare(strict_types=1);

namespace Tourze\OpenAiHttpProxyBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\OpenAiHttpProxyBundle\Exception\ClientConfigurationException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(ClientConfigurationException::class)]
final class ClientConfigurationExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionMessage(): void
    {
        $exception = new ClientConfigurationException('Invalid client configuration');

        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertEquals('Invalid client configuration', $exception->getMessage());
    }

    public function testExceptionWithCode(): void
    {
        $exception = new ClientConfigurationException('Configuration error', 500);

        $this->assertEquals('Configuration error', $exception->getMessage());
        $this->assertEquals(500, $exception->getCode());
    }

    public function testExceptionWithPrevious(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new ClientConfigurationException('Wrapped exception', 0, $previous);

        $this->assertEquals('Wrapped exception', $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testExceptionIsThrowable(): void
    {
        $this->expectException(ClientConfigurationException::class);
        $this->expectExceptionMessage('Test exception');

        throw new ClientConfigurationException('Test exception');
    }
}
