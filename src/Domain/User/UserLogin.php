<?php

declare(strict_types=1);

namespace Yef\Domain\User;

use InvalidArgumentException;
use Override;
use Stringable;

use function preg_match;
use function strtolower;
use function trim;

/**
 * What a user types to sign in. Compared without regard to case, so it is kept in lowercase.
 */
final readonly class UserLogin implements Stringable
{
    private const string PATTERN = '/^[a-z0-9][a-z0-9._-]{1,31}$/';

    public string $value;

    /**
     * @throws InvalidArgumentException When the login is not 2 to 32 letters, digits, dots, dashes or underscores.
     */
    public function __construct(string $value)
    {
        $normalized = strtolower(trim($value));
        if (preg_match(self::PATTERN, $normalized) !== 1) {
            throw new InvalidArgumentException('A login has 2 to 32 characters: letters, digits, dots, dashes and underscores.');
        }

        $this->value = $normalized;
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
