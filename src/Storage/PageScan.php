<?php

declare(strict_types=1);

namespace Yef\Storage;

use Yef\Domain\Page\PageId;
use Yef\Domain\Page\PageMetadata;

/**
 * What a walk through the page directories found.
 */
final readonly class PageScan
{
    /**
     * @param list<PageMetadata> $pages Pages with readable metadata, ordered by id.
     * @param list<PageId> $damagedPages Pages whose metadata is missing or unreadable.
     */
    public function __construct(
        public array $pages,
        public array $damagedPages,
    ) {}
}
