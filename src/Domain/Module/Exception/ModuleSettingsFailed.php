<?php

declare(strict_types=1);

namespace Yef\Domain\Module\Exception;

use RuntimeException;
use Throwable;

/**
 * The list of enabled modules could not be saved. The cause stays in `previous`.
 */
final class ModuleSettingsFailed extends RuntimeException
{
    public static function whileSaving(?Throwable $previous = null): self
    {
        return new self('Could not save the list of enabled modules.', 0, $previous);
    }
}
