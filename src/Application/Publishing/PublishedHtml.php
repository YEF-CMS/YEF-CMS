<?php

declare(strict_types=1);

namespace Yef\Application\Publishing;

use Yef\Domain\Page\PageId;
use Yef\Domain\Page\PagePath;
use Yef\Domain\Publishing\Exception\PublishingFailed;
use Yef\Domain\Publishing\PublicArtifactPublisher;
use Yef\Domain\Publishing\PublicPath;
use Yef\Domain\Publishing\PublishedPages;

/**
 * Removes the public HTML of a page address, unless a different page has been published there in the meantime.
 */
final readonly class PublishedHtml
{
    public function __construct(
        private PublicArtifactPublisher $publisher,
        private PublishedPages $published,
    ) {}

    /**
     * @throws PublishingFailed
     */
    public function remove(PagePath $path, PageId $formerOwner): void
    {
        foreach ($this->published->all() as $page) {
            if ($page->path->equals($path) && !$page->id->equals($formerOwner)) {
                return;
            }
        }

        $this->publisher->remove(PublicPath::forPage($path));
    }
}
