<?php

declare(strict_types=1);

namespace Yef\Tests\Support\Text;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Yef\Domain\ContentType\Exception\InvalidPayload;
use Yef\Domain\ContentType\Payload;

/**
 * Checks the behavior every content type has to provide, using the reference text type.
 */
#[CoversClass(TextPayload::class)]
#[CoversClass(TextPayloadFactory::class)]
#[CoversClass(TextPayloadSerializer::class)]
#[CoversClass(TextContentRenderer::class)]
#[CoversClass(TextContentType::class)]
final class TextContentTypeTest extends TestCase
{
    public function testFactoryBuildsPayloadFromInput(): void
    {
        $payload = TextContentType::definition()->factory->fromInput(['body' => '<p>Hi</p>']);

        $this->assertEquals(new TextPayload('<p>Hi</p>'), $payload);
    }

    public function testFactoryRejectsInvalidInput(): void
    {
        $this->expectException(InvalidPayload::class);

        TextContentType::definition()->factory->fromInput(['body' => ['not', 'a', 'string']]);
    }

    public function testSerializerRoundTripKeepsPayloadIntact(): void
    {
        $serializer = TextContentType::definition()->serializer;
        $payload = new TextPayload('<p>Привет, "мир"</p>' . "\n");

        $restored = $serializer->fromFiles($serializer->toFiles($payload));

        $this->assertEquals($payload, $restored);
    }

    public function testSerializerRejectsIncompleteFiles(): void
    {
        $this->expectException(InvalidPayload::class);

        TextContentType::definition()->serializer->fromFiles([]);
    }

    public function testRendererOutputsPayloadHtml(): void
    {
        $content = TextContentType::definition()->renderer->render(new TextPayload('<p>Hi</p>'));

        $this->assertSame('<p>Hi</p>', $content->html);
        $this->assertSame([], $content->assets);
    }

    public function testRolesRejectForeignPayload(): void
    {
        $foreign = new class implements Payload {};
        $definition = TextContentType::definition();

        $this->expectException(InvalidArgumentException::class);

        $definition->serializer->toFiles($foreign);
    }

    public function testRendererRejectsForeignPayload(): void
    {
        $foreign = new class implements Payload {};

        $this->expectException(InvalidArgumentException::class);

        TextContentType::definition()->renderer->render($foreign);
    }
}
