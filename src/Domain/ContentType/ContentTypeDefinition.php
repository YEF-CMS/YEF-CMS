<?php

declare(strict_types=1);

namespace Yef\Domain\ContentType;

/**
 * Everything the core needs from a content type. Every role is mandatory, so a type cannot be registered half-done.
 */
final readonly class ContentTypeDefinition
{
    public function __construct(
        public ContentTypeId $id,
        public PayloadFactory $factory,
        public PayloadSerializer $serializer,
        public ContentRenderer $renderer,
    ) {}
}
