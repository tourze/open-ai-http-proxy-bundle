<?php

declare(strict_types=1);

namespace Tourze\OpenAiHttpProxyBundle\Tests\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\AccessKeyBundle\Entity\AccessKey;
use Tourze\OpenAiHttpProxyBundle\Model\ValidationResult;

/**
 * @internal
 */
#[CoversClass(ValidationResult::class)]
final class ValidationResultTest extends TestCase
{
    public function testValidResult(): void
    {
        $apiCaller = new class extends AccessKey {
            public function __construct()
            {
            }
        };
        $result = new ValidationResult(true, null, $apiCaller);

        $this->assertTrue($result->isValid());
        $this->assertNull($result->getError());
        $this->assertSame($apiCaller, $result->getCaller());
    }

    public function testInvalidResultWithError(): void
    {
        $result = new ValidationResult(false, 'Invalid token');

        $this->assertFalse($result->isValid());
        $this->assertEquals('Invalid token', $result->getError());
        $this->assertNull($result->getCaller());
    }

    public function testInvalidResultWithoutError(): void
    {
        $result = new ValidationResult(false);

        $this->assertFalse($result->isValid());
        $this->assertNull($result->getError());
        $this->assertNull($result->getCaller());
    }

    public function testValidResultWithoutCaller(): void
    {
        $result = new ValidationResult(true);

        $this->assertTrue($result->isValid());
        $this->assertNull($result->getError());
        $this->assertNull($result->getCaller());
    }

    public function testResultWithAllParameters(): void
    {
        $apiCaller = new class extends AccessKey {
            public function __construct()
            {
            }
        };
        $result = new ValidationResult(true, 'Success message', $apiCaller);

        $this->assertTrue($result->isValid());
        $this->assertEquals('Success message', $result->getError());
        $this->assertSame($apiCaller, $result->getCaller());
    }
}
