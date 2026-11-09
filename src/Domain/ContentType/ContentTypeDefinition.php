<?php

declare(strict_types=1);

namespace Yef\Domain\ContentType;

use InvalidArgumentException;

use function trim;

/**
 * Everything the core needs from a content type. Every role is mandatory, so a type cannot be registered half-done.
 */
final readonly class ContentTypeDefinition
{
    /**
     * @param string $title Human-readable name of the type for screens ("Text page").
     *
     * @throws InvalidArgumentException When the title is empty.
     */
    public function __construct(
        public ContentTypeId $id,
        public string $title,
        public PageForm $form,
        public PayloadFactory $factory,
        public PayloadSerializer $serializer,
        public ContentRenderer $renderer,
    ) {
        if (trim($title) === '') {
            throw new InvalidArgumentException('A content type needs a title.');
        }
    }
}
