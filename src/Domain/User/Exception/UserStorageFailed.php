<?php

declare(strict_types=1);

namespace Yef\Domain\User\Exception;

use RuntimeException;
use Throwable;

/**
 * The user data or the setup code could not be read or saved. The cause stays in `previous`.
 */
final class UserStorageFailed extends RuntimeException
{
    public static function whileSaving(?Throwable $previous = null): self
    {
        return new self('The user data could not be saved.', 0, $previous);
    }

    public static function whileReading(?Throwable $previous = null): self
    {
        return new self('The user data could not be read.', 0, $previous);
    }
}
