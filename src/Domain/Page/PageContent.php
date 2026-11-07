<?php

declare(strict_types=1);

namespace Yef\Domain\Page;

use InvalidArgumentException;

use function trim;

/**
 * What an editor writes on a page: title, HTML body and SEO fields.
 *
 * @psalm-api Read by the storage and rendering layers.
 */
final readonly class PageContent
{
    public function __construct(
        public string $title,
        public string $body,
        public string $metaTitle = '',
        public string $metaDescription = '',
    ) {
        if (trim($title) === '') {
            throw new InvalidArgumentException('Page title must not be empty.');
        }
    }
}
