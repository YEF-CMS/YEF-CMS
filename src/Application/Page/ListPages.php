<?php

declare(strict_types=1);

namespace Yef\Application\Page;

use Yef\Domain\Page\PageMetadata;
use Yef\Domain\Page\PageRepository;

/**
 * @psalm-api Used by the admin and console adapters.
 */
final readonly class ListPages
{
    public function __construct(private PageRepository $pages) {}

    /**
     * @return list<PageMetadata> Ordered by path.
     */
    public function handle(): array
    {
        $pages = $this->pages->allMetadata();
        usort(
            $pages,
            static fn(PageMetadata $a, PageMetadata $b): int => $a->details->path->value <=> $b->details->path->value,
        );

        return $pages;
    }
}
