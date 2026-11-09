<?php

declare(strict_types=1);

namespace Yef\Domain\User;

use InvalidArgumentException;
use Override;
use Stringable;

use function preg_match;
use function sprintf;

/**
 * User identifier in the canonical ULID form. It does not change when the login does, so roles can refer to it.
 */
final readonly class UserId implements Stringable
{
    private const string PATTERN = '/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/';

    public string $value;

    /**
     * @throws InvalidArgumentException When the value is not a ULID.
     */
    public function __construct(string $value)
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidArgumentException(sprintf('"%s" is not a valid user id.', $value));
        }

        $this->value = $value;
    }

    #[Override]
    public function __toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
