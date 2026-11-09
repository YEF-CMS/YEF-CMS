<?php

declare(strict_types=1);

namespace Yef\Application\Page;

/**
 * How many pages the site has.
 */
final readonly class PageSummary
{
    public function __construct(
        public int $total,
        public int $published,
    ) {}

    public function drafts(): int
    {
        return $this->total - $this->published;
    }
}
