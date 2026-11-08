<?php

declare(strict_types=1);

namespace Yef\Application\Page;

use Yef\Domain\Page\Exception\PageStorageFailed;
use Yef\Domain\Page\IntegrityReport;
use Yef\Domain\Page\PageStorageIntegrity;

/**
 * Heals the page storage after interrupted writes and reports what still needs attention.
 *
 * @psalm-api Used by the admin and console adapters.
 */
final readonly class RepairPageStorage
{
    public function __construct(private PageStorageIntegrity $integrity) {}

    /**
     * @throws PageStorageFailed
     */
    public function handle(): IntegrityReport
    {
        return $this->integrity->repair();
    }
}
