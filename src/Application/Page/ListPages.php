<?php

declare(strict_types=1);

namespace Yef\Application\Page;

use Yef\Domain\Page\Page;
use Yef\Domain\Page\PageRepository;

/**
 * @psalm-api Used by the admin and console adapters.
 */
final readonly class ListPages
{
    public function __construct(private PageRepository $pages) {}

    /**
     * @return list<Page> Ordered by path.
     */
    public function __invoke(): array
    {
        $pages = $this->pages->all();
        usort($pages, static fn(Page $a, Page $b): int => $a->path->value <=> $b->path->value);

        return $pages;
    }
}
