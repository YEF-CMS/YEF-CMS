<?php

declare(strict_types=1);

namespace Yef\Application\Page;

use InvalidArgumentException;
use Yef\Domain\Page\Exception\PageNotFound;
use Yef\Domain\Page\PageId;
use Yef\Domain\Page\PageMetadata;
use Yef\Domain\Page\PageRepository;
use Yef\Domain\Publishing\Exception\PublishingFailed;
use Yef\Application\Publishing\UnpublishPage;

/**
 * Deletes a page together with its public HTML, so the site never keeps a page that no longer exists.
 *
 * @psalm-api Used by the admin and console adapters.
 */
final readonly class DeletePage
{
    public function __construct(
        private PageRepository $pages,
        private UnpublishPage $unpublishPage,
    ) {}

    /**
     * @throws InvalidArgumentException When the id is invalid.
     * @throws PageNotFound
     * @throws PublishingFailed When the public HTML could not be removed; the page is kept then.
     */
    public function handle(string $id): void
    {
        $pageId = new PageId($id);

        foreach ($this->pages->allMetadata() as $metadata) {
            if ($metadata->id->equals($pageId)) {
                $this->unpublishPage->handle($metadata->details->path);
            }
        }

        $this->pages->delete($pageId);
    }
}
