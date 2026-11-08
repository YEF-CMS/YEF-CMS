<?php

declare(strict_types=1);

namespace Yef\Application\Publishing;

use Yef\Domain\Page\PageId;

use function count;

/**
 * Outcome of publishing every page once.
 *
 * @psalm-api Read by the console adapter.
 */
final readonly class RebuildReport
{
    /**
     * @param array<string, int> $counts Pages per `PublishResult` value.
     * @param list<PageId> $failed Pages that could not be published; the rest was still processed.
     */
    public function __construct(
        private array $counts,
        public array $failed,
    ) {}

    public function count(PublishResult $result): int
    {
        return $this->counts[$result->value] ?? 0;
    }

    public function hasFailures(): bool
    {
        return count($this->failed) > 0;
    }
}
