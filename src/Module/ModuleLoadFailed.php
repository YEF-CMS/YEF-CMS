<?php

declare(strict_types=1);

namespace Yef\Module;

use RuntimeException;
use Throwable;

/**
 * An enabled module could not be registered. Only the loader sees it: it becomes the reason in the module's status.
 */
final class ModuleLoadFailed extends RuntimeException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, 0, $previous);
    }
}
