<?php

declare(strict_types=1);

namespace Yef\Application\User;

use RuntimeException;

final class InvalidSetupToken extends RuntimeException
{
    public static function create(): self
    {
        return new self('The setup code is not right.');
    }
}
