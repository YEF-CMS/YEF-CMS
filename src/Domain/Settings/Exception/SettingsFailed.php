<?php

declare(strict_types=1);

namespace Yef\Domain\Settings\Exception;

use RuntimeException;
use Throwable;

/**
 * The site settings could not be saved. Says nothing about where or how they are kept.
 */
final class SettingsFailed extends RuntimeException
{
    public static function whileSaving(Throwable $previous): self
    {
        return new self('The site settings could not be saved.', 0, $previous);
    }
}
