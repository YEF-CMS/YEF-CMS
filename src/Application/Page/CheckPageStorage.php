<?php

declare(strict_types=1);

namespace Yef\Application\Page;

use Yef\Domain\Page\Exception\PageStorageFailed;
use Yef\Domain\Page\IntegrityReport;
use Yef\Domain\Page\PageStorageIntegrity;

/**
 * Reports problems of the page storage without changing anything.
 *
 * @psalm-api Used by the admin and console adapters.
 */
final readonly class CheckPageStorage
{
    public function __construct(private PageStorageIntegrity $integrity) {}

    /**
     * @throws PageStorageFailed
     */
    public function handle(): IntegrityReport
    {
        return $this->integrity->check();
    }
}
