<?php

declare(strict_types=1);

namespace Yef\Admin\Pages;

use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use Yef\Admin\Http\Form;
use Yef\Domain\ContentType\ContentTypeDefinition;
use Yef\Domain\ContentType\FormField;
use Yef\Domain\Page\Page;

use function array_map;
use function trim;

/**
 * What the page form holds: the fields every page has and the fields of its content type.
 * It is what the form shows when it is opened and what it shows again when something submitted was wrong.
 */
final readonly class SubmittedPage
{
    /**
     * @param array<string, string> $fields Values of the content type's fields, by field name.
     */
    private function __construct(
        public string $title,
        public string $path,
        public string $metaTitle,
        public string $metaDescription,
        public bool $published,
        public array $fields,
    ) {}

    /**
     * An empty form for a new page.
     */
    public static function blank(ContentTypeDefinition $type): self
    {
        return new self('', '', '', '', false, self::emptyFields($type));
    }

    /**
     * The form of an existing page, filled with what it has now.
     *
     * @throws InvalidArgumentException When the page does not belong to the content type.
     */
    public static function of(Page $page, ContentTypeDefinition $type): self
    {
        $details = $page->metadata->details;

        return new self(
            $details->title,
            $details->path->value,
            $details->metaTitle,
            $details->metaDescription,
            $page->metadata->isPublished(),
            $type->form->valuesOf($page->payload),
        );
    }

    /**
     * What a visitor of the form sent. The address may be typed with slashes around it; they mean nothing.
     */
    public static function fromRequest(ServerRequestInterface $request, ContentTypeDefinition $type): self
    {
        $form = Form::of($request);
        $section = $form->section('fields');
        $fields = [];
        foreach ($type->form->fields() as $field) {
            $fields[$field->name] = $section->text($field->name);
        }

        return new self(
            trim($form->text('title')),
            trim($form->text('path'), " \t/"),
            trim($form->text('meta_title')),
            trim($form->text('meta_description')),
            $form->text('published') === '1',
            $fields,
        );
    }

    /**
     * @return array<string, string>
     */
    private static function emptyFields(ContentTypeDefinition $type): array
    {
        $fields = [];
        foreach (array_map(static fn(FormField $field): string => $field->name, $type->form->fields()) as $name) {
            $fields[$name] = '';
        }

        return $fields;
    }
}
