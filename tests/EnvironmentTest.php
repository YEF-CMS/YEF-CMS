<?php

declare(strict_types=1);

namespace Yef\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Yef\Environment;

#[CoversClass(Environment::class)]
final class EnvironmentTest extends TestCase
{
    public function testDefaultsToProductionWithoutDebug(): void
    {
        $environment = Environment::fromValues(null, null);

        $this->assertSame(Environment::PROD, $environment->name);
        $this->assertFalse($environment->debug);
    }

    public function testEmptyNameFallsBackToProduction(): void
    {
        $this->assertSame(Environment::PROD, Environment::fromValues('', null)->name);
    }

    public function testReadsNameAndDebugFlag(): void
    {
        $environment = Environment::fromValues('dev', 'true');

        $this->assertSame(Environment::DEV, $environment->name);
        $this->assertTrue($environment->debug);
    }

    public function testUnrecognizedDebugValueMeansDisabled(): void
    {
        $this->assertFalse(Environment::fromValues('test', 'maybe')->debug);
    }

    public function testUnknownNameIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Environment::fromValues('staging', null);
    }
}
