<?php

declare(strict_types=1);

namespace Yef\Domain\Publishing;

use Yef\Domain\Page\ContentVersion;
use Yef\Domain\Page\PageId;
use Yef\Domain\Page\PagePath;

/**
 * What is known about the public HTML of a page: where it lies and which version of the page it was built from.
 */
final readonly class PublishedPage
{
    public function __construct(
        public PageId $id,
        public PagePath $path,
        public ContentVersion $version,
    ) {}
}
