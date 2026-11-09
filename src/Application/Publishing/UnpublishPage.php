<?php

declare(strict_types=1);

namespace Yef\Application\Publishing;

use Yef\Domain\Page\PageId;
use Yef\Domain\Publishing\Exception\PublishingFailed;
use Yef\Domain\Publishing\PublishedPages;

/**
 * Takes down the public HTML of a page, wherever it was published, for example after the page was deleted.
 *
 * @psalm-api Used by the admin and console adapters.
 */
final readonly class UnpublishPage
{
    public function __construct(
        private PublishedPages $published,
        private PublishedHtml $html,
    ) {}

    /**
     * Does nothing for a page that is not published.
     *
     * @throws PublishingFailed
     */
    public function handle(PageId $id): void
    {
        $page = $this->published->find($id);
        if (!$page instanceof \Yef\Domain\Publishing\PublishedPage) {
            return;
        }

        $this->html->remove($page->path, $id);
        $this->published->forget($id);
    }
}
