<?php

declare(strict_types=1);

namespace Yef\Application\Page;

final readonly class CreatePageCommand
{
    public function __construct(
        public string $path,
        public string $title,
        public string $body,
        public string $metaTitle = '',
        public string $metaDescription = '',
    ) {}
}
