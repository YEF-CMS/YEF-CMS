<?php

declare(strict_types=1);

namespace Yef\Tests\Modules\TextPages;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Yef\Domain\ContentType\Exception\InvalidPayload;
use Yef\Domain\ContentType\Payload;
use Yef\Domain\ContentType\PayloadData;
use Yef\Tests\Support\TestTexts;
use YefCms\TextPages\TextContentRenderer;
use YefCms\TextPages\TextContentType;
use YefCms\TextPages\TextPayload;
use YefCms\TextPages\TextPayloadFactory;
use YefCms\TextPages\TextPayloadSerializer;

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
        $payload = TextContentType::definition(TestTexts::textModule())->factory->fromInput(['body' => '<p>Hi</p>']);

        $this->assertEquals(new TextPayload('<p>Hi</p>'), $payload);
    }

    public function testFactoryRejectsInvalidInput(): void
    {
        $this->expectException(InvalidPayload::class);

        TextContentType::definition(TestTexts::textModule())->factory->fromInput(['body' => ['not', 'a', 'string']]);
    }

    public function testSerializerRoundTripKeepsPayloadIntact(): void
    {
        $serializer = TextContentType::definition(TestTexts::textModule())->serializer;
        $payload = new TextPayload('<p>Привет, "мир"</p>' . "\n");

        $restored = $serializer->fromData($serializer->toData($payload));

        $this->assertEquals($payload, $restored);
    }

    public function testSerializerRejectsIncompleteData(): void
    {
        $this->expectException(InvalidPayload::class);

        TextContentType::definition(TestTexts::textModule())->serializer->fromData(new PayloadData([]));
    }

    public function testRendererOutputsPayloadHtml(): void
    {
        $content = TextContentType::definition(TestTexts::textModule())->renderer->render(new TextPayload('<p>Hi</p>'));

        $this->assertSame('<p>Hi</p>', $content->html);
        $this->assertSame([], $content->assets);
    }

    public function testRolesRejectForeignPayload(): void
    {
        $foreign = new class implements Payload {};
        $definition = TextContentType::definition(TestTexts::textModule());

        $this->expectException(InvalidArgumentException::class);

        $definition->serializer->toData($foreign);
    }

    public function testRendererRejectsForeignPayload(): void
    {
        $foreign = new class implements Payload {};

        $this->expectException(InvalidArgumentException::class);

        TextContentType::definition(TestTexts::textModule())->renderer->render($foreign);
    }
}
