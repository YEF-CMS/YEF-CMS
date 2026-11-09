<?php

declare(strict_types=1);

namespace Yef\Application\Page;

use Yef\Domain\Page\Exception\PageStorageFailed;
use Yef\Domain\Page\PageMetadata;
use Yef\Domain\Page\PageRepository;

use function array_filter;
use function count;

/**
 * Counts the pages of the site for the dashboard.
 *
 * @psalm-api Used by the admin adapter.
 */
final readonly class SummarizePages
{
    public function __construct(private PageRepository $pages) {}

    /**
     * @throws PageStorageFailed
     */
    public function handle(): PageSummary
    {
        $all = $this->pages->allMetadata();

        return new PageSummary(
            count($all),
            count(array_filter($all, static fn(PageMetadata $page): bool => $page->isPublished())),
        );
    }
}
