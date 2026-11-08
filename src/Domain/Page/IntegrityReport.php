<?php

declare(strict_types=1);

namespace Yef\Domain\Page;

/**
 * What is wrong with the stored pages, in terms of the domain and not of any storage format.
 */
final readonly class IntegrityReport
{
    /**
     * @param int $leftovers Remains of interrupted operations that no page depends on.
     * @param list<PageId> $damagedPages Pages that cannot be read back and need a human decision.
     */
    public function __construct(
        public int $leftovers,
        public array $damagedPages,
    ) {}

    public function isHealthy(): bool
    {
        return $this->leftovers === 0 && $this->damagedPages === [];
    }
}
