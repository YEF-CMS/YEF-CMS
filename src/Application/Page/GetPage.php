<?php

declare(strict_types=1);

namespace Yef\Application\Page;

use InvalidArgumentException;
use Yef\Domain\ContentType\ContentTypeRegistry;
use Yef\Domain\Page\Exception\PageDataCorrupted;
use Yef\Domain\Page\Exception\PageNotFound;
use Yef\Domain\Page\Exception\PageStorageFailed;
use Yef\Domain\Page\PageId;
use Yef\Domain\Page\PageRepository;

/**
 * Loads a page for a screen that shows or edits it.
 *
 * @psalm-api Used by the admin adapter.
 */
final readonly class GetPage
{
    public function __construct(
        private PageRepository $pages,
        private ContentTypeRegistry $types,
    ) {}

    /**
     * @throws InvalidArgumentException When the id is not a page id.
     * @throws PageNotFound
     * @throws PageDataCorrupted
     * @throws PageStorageFailed
     */
    public function handle(string $id): PageToEdit
    {
        $page = $this->pages->get(new PageId($id));

        return new PageToEdit(
            $page,
            $this->types->has($page->metadata->type) ? $this->types->get($page->metadata->type) : null,
        );
    }
}
