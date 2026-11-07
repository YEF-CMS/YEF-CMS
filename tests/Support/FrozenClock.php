<?php

declare(strict_types=1);

namespace Yef\Tests\Support;

use DateTimeImmutable;
use Override;
use Yef\Domain\Clock;

final class FrozenClock implements Clock
{
    public function __construct(private DateTimeImmutable $now = new DateTimeImmutable('2026-11-08 12:00:00 UTC')) {}

    #[Override]
    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function moveTo(string $time): void
    {
        $this->now = new DateTimeImmutable($time);
    }
}
