<?php

declare(strict_types=1);

namespace Yef\Domain\Page;

use Yef\Domain\Page\Exception\PageDataCorrupted;
use Yef\Domain\Page\Exception\PageNotFound;
use Yef\Domain\Page\Exception\PathAlreadyTaken;

interface PageRepository
{
    /**
     * Creates the page or replaces the stored one with the same id.
     *
     * @throws PathAlreadyTaken When another page already uses the path.
     */
    public function save(Page $page): void;

    /**
     * A page whose content type is not registered is returned with an UnavailablePayload.
     *
     * @throws PageNotFound
     * @throws PageDataCorrupted
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
