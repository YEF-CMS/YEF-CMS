<?php

declare(strict_types=1);

namespace Yef\Setup;

final readonly class CheckReport
{
    /**
     * @param list<CheckResult> $results
     */
    public function __construct(public array $results) {}

    public function hasFailures(): bool
    {
        return array_any($this->results, static fn(CheckResult $r): bool => $r->status === CheckStatus::Fail);
    }
}
