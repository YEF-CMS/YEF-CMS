<?php

declare(strict_types=1);

namespace Yef\Storage;

use Yef\Domain\Page\ContentVersion;
use Yef\Domain\Page\PageMetadata;

/**
 * What the index remembers about a page: its metadata and the version of its content.
 */
final readonly class IndexedPage
{
    public function __construct(
        public PageMetadata $metadata,
        public ContentVersion $version,
    ) {}
}
