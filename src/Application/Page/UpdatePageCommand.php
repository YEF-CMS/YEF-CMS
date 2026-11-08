<?php

declare(strict_types=1);

namespace Yef\Application\Page;

final readonly class UpdatePageCommand
{
    /**
     * @param array<string, mixed> $input Type-specific form data, passed to the content type as is.
     */
    public function __construct(
        public string $id,
        public string $path,
        public string $title,
        public array $input,
        public bool $published,
        public string $metaTitle = '',
        public string $metaDescription = '',
    ) {}
}
