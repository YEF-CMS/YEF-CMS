<?php

declare(strict_types=1);

namespace Yef\Domain\Page;

use InvalidArgumentException;
use Override;
use Stringable;

use function preg_match;
use function sprintf;

/**
 * Page identifier in the canonical ULID form (26 characters of Crockford base32).
 */
final readonly class PageId implements Stringable
{
    private const string PATTERN = '/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/';

    public string $value;

    public function __construct(string $value)
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidArgumentException(sprintf('"%s" is not a valid ULID.', $value));
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
