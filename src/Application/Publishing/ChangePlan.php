<?php

declare(strict_types=1);

namespace Yef\Application\Publishing;

use function array_filter;
use function array_values;
use function count;

/**
 * What applying changes would do. Removals come first, so addresses are free before pages are built on them.
 *
 * @psalm-api Read by the admin and console adapters.
 */
final readonly class ChangePlan
{
    /**
     * @param list<Change> $changes
     */
    public function __construct(public array $changes) {}

    public function isEmpty(): bool
    {
        return count($this->changes) === 0;
    }

    /**
     * @return list<Change>
     */
    public function withAction(ChangeAction $action): array
    {
        return array_values(array_filter($this->changes, static fn(Change $change): bool => $change->action === $action));
    }
}
