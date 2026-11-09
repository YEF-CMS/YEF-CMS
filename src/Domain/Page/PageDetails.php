<?php

declare(strict_types=1);

namespace Yef\Domain\Page;

use Yef\Domain\Page\Exception\InvalidPageDetails;

use function trim;

/**
 * What an editor controls about a page besides its type-specific payload.
 */
final readonly class PageDetails
{
    public function __construct(
        public PagePath $path,
        public string $title,
        public string $metaTitle = '',
        public string $metaDescription = '',
    ) {
        if (trim($title) === '') {
            throw InvalidPageDetails::titleIsEmpty();
        }
    }
}
