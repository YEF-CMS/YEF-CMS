<?php

declare(strict_types=1);

namespace Yef\Application\Page;

final readonly class CreatePageCommand
{
    /**
     * @param array<string, mixed> $input Type-specific form data, passed to the content type as is.
     */
    public function __construct(
        public string $type,
        public string $path,
        public string $title,
        public array $input,
        public string $metaTitle = '',
        public string $metaDescription = '',
        public bool $published = false,
    ) {}
}
