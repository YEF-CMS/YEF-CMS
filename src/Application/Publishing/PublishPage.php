<?php

declare(strict_types=1);

namespace Yef\Application\Publishing;

use Yef\Domain\ContentType\ContentTypeRegistry;
use Yef\Domain\ContentType\UnavailablePayload;
use Yef\Domain\Page\Exception\PageDataCorrupted;
use Yef\Domain\Page\Exception\PageNotFound;
use Yef\Domain\Page\Exception\PageStorageFailed;
use Yef\Domain\Page\Page;
use Yef\Domain\Page\PageId;
use Yef\Domain\Page\PageRepository;
use Yef\Domain\Publishing\Exception\PublishingFailed;
use Yef\Domain\Publishing\PublicArtifactPublisher;
use Yef\Domain\Publishing\PublicPath;
use Yef\Domain\Rendering\Exception\RenderingFailed;
use Yef\Domain\Rendering\PageRenderer;

/**
 * Makes the public HTML of a page match the page: builds it for a published page, removes it for a draft.
 *
 * @psalm-api Used by the admin and console adapters.
 */
final readonly class PublishPage
{
    public function __construct(
        private PageRepository $pages,
        private ContentTypeRegistry $contentTypes,
        private PageRenderer $renderer,
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
        $page = $this->pages->get(new PageId($id));

        if ($page->payload instanceof UnavailablePayload) {
            return PublishResult::Skipped;
        }

        $path = PublicPath::forPage($page->metadata->details->path);

        if (!$page->metadata->isPublished()) {
            $this->publisher->remove($path);

            return PublishResult::Withdrawn;
        }

        $this->publisher->publish($path, $this->render($page));

        return PublishResult::Published;
    }

    private function render(Page $page): string
    {
        $content = $this->contentTypes->get($page->metadata->type)->renderer->render($page->payload);

        return $this->renderer->render($page->metadata, $content);
    }
}
