<?php

declare(strict_types=1);

namespace Yef\Domain\User\Exception;

use RuntimeException;
use Yef\Domain\User\UserLogin;

use function sprintf;

final class UserNotFound extends RuntimeException
{
    public static function withLogin(UserLogin $login): self
    {
        return new self(sprintf('There is no user "%s".', $login));
    }
}
