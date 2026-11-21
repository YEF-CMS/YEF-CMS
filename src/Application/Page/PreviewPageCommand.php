<?php

declare(strict_types=1);

namespace Yef\Application\Page;

/**
 * A page as it stands in the form, saved or not.
 */
final readonly class PreviewPageCommand
{
    /**
     * @param array<string, mixed> $input Type-specific form data, passed to the content type as is.
     */
    public function __construct(
        public string $type,
        public string $title,
        public array $input,
        public string $metaTitle = '',
        public string $metaDescription = '',
    ) {}
}
