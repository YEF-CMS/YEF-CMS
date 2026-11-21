<?php

declare(strict_types=1);

namespace Yef\Tests\Domain\ContentType;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Yef\Domain\ContentType\ContentTypeId;
use Yef\Domain\ContentType\ContentTypeRegistry;
use Yef\Domain\ContentType\Exception\UnknownContentType;
use Yef\Tests\Support\TestTexts;
use YefCms\TextPages\TextContentType;

#[CoversClass(ContentTypeRegistry::class)]
#[CoversClass(UnknownContentType::class)]
final class ContentTypeRegistryTest extends TestCase
{
    public function testReturnsRegisteredDefinition(): void
    {
        $definition = TextContentType::definition(TestTexts::textModule());

        $registry = new ContentTypeRegistry([$definition]);

        $this->assertSame($definition, $registry->get(new ContentTypeId(TextContentType::ID)));
    }

    public function testTellsWhetherTypeIsRegistered(): void
    {
        $registry = TextContentType::registry(TestTexts::textModule());

        $this->assertTrue($registry->has(new ContentTypeId(TextContentType::ID)));
        $this->assertFalse($registry->has(new ContentTypeId('gallery')));
    }

    public function testUnknownTypeIsReported(): void
    {
        $this->expectException(UnknownContentType::class);

        new ContentTypeRegistry([])->get(new ContentTypeId('gallery'));
    }

    public function testRejectsTwoTypesWithTheSameId(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ContentTypeRegistry([TextContentType::definition(TestTexts::textModule()), TextContentType::definition(TestTexts::textModule())]);
    }
}
