<?php

declare(strict_types=1);

namespace Yef\Application\User;

use InvalidArgumentException;

use function sprintf;

/**
 * A password does not meet the rules. `rule` and `limit` let screens say which rule in the user's language;
 * the message is the same in English for the console and logs.
 */
final class WeakPassword extends InvalidArgumentException
{
    public const string TOO_SHORT = 'too_short';

    public const string TOO_LONG = 'too_long';

    private function __construct(
        public readonly string $rule,
        public readonly int $limit,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function tooShort(int $minLength): self
    {
        return new self(self::TOO_SHORT, $minLength, sprintf('The password must have at least %d characters.', $minLength));
    }

    public static function tooLong(int $maxBytes): self
    {
        return new self(self::TOO_LONG, $maxBytes, sprintf('The password must not be longer than %d bytes.', $maxBytes));
    }
}
