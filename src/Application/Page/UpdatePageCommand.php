<?php

declare(strict_types=1);

namespace Yef\Application\Page;

final readonly class UpdatePageCommand
{
    public function __construct(
        public string $id,
        public string $path,
        public string $title,
        public string $body,
        public bool $published,
        public string $metaTitle = '',
        public string $metaDescription = '',
    ) {}
}
