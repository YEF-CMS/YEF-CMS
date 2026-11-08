<?php

declare(strict_types=1);

namespace Yef\Domain\Page;

use Yef\Domain\Page\Exception\PageNotFound;

interface PageRepository
{
    /**
     * Creates the page or replaces the stored one with the same id.
     */
    public function save(Page $page): void;

    /**
     * @throws PageNotFound
     */
    public function get(PageId $id): Page;

    public function findMetadataByPath(PagePath $path): ?PageMetadata;

    /**
     * @return list<PageMetadata>
     */
    public function allMetadata(): array;

    /**
     * @throws PageNotFound
     */
    public function delete(PageId $id): void;
}
