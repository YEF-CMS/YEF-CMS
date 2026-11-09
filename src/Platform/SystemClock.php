<?php

declare(strict_types=1);

namespace Yef\Platform;

use DateTimeImmutable;
use Override;
use Yef\Domain\Clock;

/**
 * @psalm-api Wired in through the DI configuration.
 */
final readonly class SystemClock implements Clock
{
    #[Override]
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
