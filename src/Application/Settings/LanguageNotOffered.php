<?php

declare(strict_types=1);

namespace Yef\Application\Settings;

use DomainException;

use function sprintf;

/**
 * The admin is not available in the language that was asked for.
 */
final class LanguageNotOffered extends DomainException
{
    public static function withCode(string $code): self
    {
        return new self(sprintf('The admin is not available in "%s".', $code));
    }
}
