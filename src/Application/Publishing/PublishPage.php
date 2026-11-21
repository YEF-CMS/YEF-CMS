<?php

declare(strict_types=1);

namespace Yef\Application\Publishing;

use Yef\Domain\ContentType\UnavailablePayload;
use Yef\Domain\Page\Exception\PageDataCorrupted;
use Yef\Domain\Page\Exception\PageNotFound;
use Yef\Domain\Page\Exception\PageStorageFailed;
use Yef\Domain\Page\PageId;
use Yef\Domain\Page\PageRepository;
use Yef\Domain\Page\PageVersions;
use Yef\Domain\Publishing\Exception\PublishingFailed;
use Yef\Domain\Publishing\PublicArtifactPublisher;
use Yef\Domain\Publishing\PublicPath;
use Yef\Domain\Publishing\PublishedPage;
use Yef\Domain\Publishing\PublishedPages;
use Yef\Domain\Rendering\Exception\RenderingFailed;

/**
 * Makes the public HTML of a page match the page: builds it for a published page, removes it for a draft.
 * Remembers what was published, so a moved or withdrawn page leaves no HTML behind.
 *
 * @psalm-api Used by the admin and console adapters.
 */
final readonly class PublishPage
{
    public function __construct(
        private PageRepository $pages,
        private PageVersions $versions,
        private PublishedPages $published,
        private PublishedHtml $html,
        private RenderPage $renderPage,
        private PublicArtifactPublisher $publisher,
    ) {}

    /**
     * @throws PageNotFound
     * @throws PageDataCorrupted
     * @throws PageStorageFailed
     * @throws RenderingFailed
     * @throws PublishingFailed
     */
    public function handle(string $id): PublishResult
    {
        $pageId = new PageId($id);
        // Read first: a page saved in between then only makes the recorded version older than the built HTML,
        // which at worst builds the page once more, and never hides a change.
        $version = $this->versions->versionOf($pageId);
        $page = $this->pages->get($pageId);

        if ($page->payload instanceof UnavailablePayload) {
            return PublishResult::Skipped;
        }

        $path = $page->metadata->details->path;
        $previous = $this->published->find($pageId);

        if (!$page->metadata->isPublished()) {
            $this->html->remove($path, $pageId);
            if ($previous instanceof PublishedPage) {
                $this->html->remove($previous->path, $pageId);
                $this->published->forget($pageId);
            }

            return PublishResult::Withdrawn;
        }

        $this->publisher->publish(PublicPath::forPage($path), $this->renderPage->handle($page->metadata, $page->payload));
        if ($previous instanceof PublishedPage && !$previous->path->equals($path)) {
            $this->html->remove($previous->path, $pageId);
        }

        $this->published->record(new PublishedPage($pageId, $path, $version));

        return PublishResult::Published;
    }
}
