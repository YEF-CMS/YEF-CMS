<?php

declare(strict_types=1);

namespace Yef\Application\Publishing;

use Yef\Domain\Page\PageId;

use function count;

/**
 * Outcome of applying changes.
 *
 * @psalm-api Read by the admin and console adapters.
 */
final readonly class ApplyReport
{
    /**
     * @param list<PageId> $failed Pages whose change could not be applied; the other changes were still applied.
     */
    public function __construct(
        public int $built,
        public int $removed,
        public int $skipped,
        public array $failed,
    ) {}

    public function hasFailures(): bool
    {
        return count($this->failed) > 0;
    }
}
