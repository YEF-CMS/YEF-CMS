<?php

declare(strict_types=1);

namespace Yef\Application\User;

use RuntimeException;

/**
 * The first-run setup was asked for although the site already has a user.
 */
final class SetupNotAllowed extends RuntimeException
{
    public static function becauseUsersExist(): self
    {
        return new self('The site is already set up.');
    }
}
