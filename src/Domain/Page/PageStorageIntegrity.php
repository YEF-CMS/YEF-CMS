<?php

declare(strict_types=1);

namespace Yef\Domain\Page;

use Yef\Domain\Page\Exception\PageStorageFailed;

/**
 * Checks and heals the storage of pages after crashes, timeouts and interrupted writes.
 */
interface PageStorageIntegrity
{
    /**
     * Only looks, changes nothing.
     *
     * @throws PageStorageFailed
     */
    public function check(): IntegrityReport;

    /**
     * Removes the leftovers, restores pages that an interrupted write left without a current version
     * and rebuilds whatever the storage derives from the pages.
     *
     * @return IntegrityReport What is still wrong afterwards: damaged pages are never touched.
     *
     * @throws PageStorageFailed
     */
    public function repair(): IntegrityReport;
}
