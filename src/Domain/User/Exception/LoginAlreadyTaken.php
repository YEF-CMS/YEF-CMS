<?php

declare(strict_types=1);

namespace Yef\Domain\User\Exception;

use RuntimeException;
use Yef\Domain\User\UserLogin;

use function sprintf;

final class LoginAlreadyTaken extends RuntimeException
{
    public static function byLogin(UserLogin $login): self
    {
        return new self(sprintf('The login "%s" is already taken.', $login));
    }
}
