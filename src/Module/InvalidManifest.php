<?php

declare(strict_types=1);

namespace Yef\Module;

use RuntimeException;
use Throwable;

/**
 * A module manifest cannot be used; the message says why in words fit for the module author.
 */
final class InvalidManifest extends RuntimeException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, 0, $previous);
    }
}
