<?php

declare(strict_types=1);

namespace Yef\Storage;

use Yef\Domain\Page\PageId;

/**
 * What a walk through the page directories found.
 */
final readonly class PageScan
{
    /**
     * @param list<IndexedPage> $pages Readable pages, ordered by id.
     * @param list<PageId> $damagedPages Pages whose metadata or payload is missing or unreadable.
     */
    public function __construct(
        public array $pages,
        public array $damagedPages,
    ) {}
}
