<?php

declare(strict_types=1);

namespace Yef\Domain\Page;

use Yef\Domain\Page\Exception\PageNotFound;

/**
 * The current content version of every page, available without reading page payloads.
 */
interface PageVersions
{
    /**
     * @throws PageNotFound
     */
    public function versionOf(PageId $id): ContentVersion;

    /**
     * @return array<string, ContentVersion> Page id => its version.
     */
    public function all(): array;
}
