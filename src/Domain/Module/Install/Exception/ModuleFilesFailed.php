<?php

declare(strict_types=1);

namespace Yef\Domain\Module\Install\Exception;

use RuntimeException;
use Throwable;

use function sprintf;

/**
 * The files of the modules could not be changed.
 */
final class ModuleFilesFailed extends RuntimeException
{
    public static function because(string $what, ?Throwable $previous = null): self
    {
        return new self(sprintf('The files of the modules could not be changed: %s', $what), 0, $previous);
    }
}
