<?php

declare(strict_types=1);

namespace Yef\Domain\ContentType;

use InvalidArgumentException;

/**
 * The form a content type shows for its pages: the fields of its payload. The admin adds the fields every page has
 * (title, address, SEO, status) around them. What the form submits goes to `PayloadFactory::fromInput()`.
 */
interface PageForm
{
    /**
     * @return list<FormField>
     */
    public function fields(): array;

    /**
     * What to put in the fields when an existing page is edited.
     *
     * @return array<string, string> Field name => value.
     *
     * @throws InvalidArgumentException When the payload was not created by this content type.
     */
    public function valuesOf(Payload $payload): array;
}
