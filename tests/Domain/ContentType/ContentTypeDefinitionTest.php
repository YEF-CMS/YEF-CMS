<?php

declare(strict_types=1);

namespace Yef\Tests\Domain\ContentType;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yef\Domain\ContentType\ContentTypeDefinition;
use Yef\Domain\ContentType\ContentTypeId;
use Yef\Domain\ContentType\ContentTypeRegistry;
use Yef\Domain\ContentType\FieldKind;
use Yef\Domain\ContentType\FormField;
use YefCms\TextPages\TextContentRenderer;
use YefCms\TextPages\TextContentType;
use YefCms\TextPages\TextPageForm;
use YefCms\TextPages\TextPayload;
use YefCms\TextPages\TextPayloadFactory;
use YefCms\TextPages\TextPayloadSerializer;

#[CoversClass(ContentTypeDefinition::class)]
#[CoversClass(FormField::class)]
#[CoversClass(ContentTypeRegistry::class)]
final class ContentTypeDefinitionTest extends TestCase
{
    public function testFieldKeepsWhatTheFormNeedsToDrawIt(): void
    {
        $field = new FormField('body', FieldKind::RichText, 'Content', true);

        $this->assertSame('body', $field->name);
        $this->assertSame(FieldKind::RichText, $field->kind);
        $this->assertSame('Content', $field->label);
        $this->assertTrue($field->required);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidFields(): iterable
    {
        yield 'upper case name' => ['Body', 'Content'];
        yield 'name with a space' => ['my body', 'Content'];
        yield 'name starting with a digit' => ['1body', 'Content'];
        yield 'empty label' => ['body', '  '];
    }

    #[DataProvider('invalidFields')]
    public function testRejectsAnInvalidField(string $name, string $label): void
    {
        $this->expectException(InvalidArgumentException::class);

        new FormField($name, FieldKind::Text, $label);
    }

    public function testTypeNeedsATitleUsersCanRead(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ContentTypeDefinition(
            new ContentTypeId('text'),
            ' ',
            new TextPageForm(),
            new TextPayloadFactory(),
            new TextPayloadSerializer(),
            new TextContentRenderer(),
        );
    }

    public function testRegistryListsTheTypesInTheOrderTheyWereRegistered(): void
    {
        $text = TextContentType::definition();
        $other = new ContentTypeDefinition(
            new ContentTypeId('news'),
            'News item',
            new TextPageForm(),
            new TextPayloadFactory(),
            new TextPayloadSerializer(),
            new TextContentRenderer(),
        );

        $registry = new ContentTypeRegistry([$other, $text]);

        $this->assertSame(['news', 'text'], array_map(static fn(ContentTypeDefinition $type): string => $type->id->value, $registry->all()));
        $this->assertSame([], new ContentTypeRegistry([])->all());
    }

    public function testTextPageFormHasOneRichTextFieldAndFillsItFromThePayload(): void
    {
        $form = TextContentType::definition()->form;

        $this->assertSame(['body'], array_map(static fn(FormField $field): string => $field->name, $form->fields()));
        $this->assertSame([FieldKind::RichText], array_map(static fn(FormField $field): FieldKind => $field->kind, $form->fields()));
        $this->assertSame(['body' => '<p>Hi</p>'], $form->valuesOf(new TextPayload('<p>Hi</p>')));
    }

    public function testTextPageFormRefusesAPayloadOfAnotherType(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TextContentType::definition()->form->valuesOf(new \Yef\Domain\ContentType\UnavailablePayload(
            new ContentTypeId('gallery'),
            new \Yef\Domain\ContentType\PayloadData([]),
        ));
    }
}
