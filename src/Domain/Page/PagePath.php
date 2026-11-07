<?php

declare(strict_types=1);

namespace Yef\Domain\Page;

use InvalidArgumentException;
use Override;
use Stringable;

use function explode;
use function in_array;
use function preg_match;
use function sprintf;
use function strlen;

/**
 * Address of a page: the path under the document root where its HTML is published ("blog/hello").
 * The empty path is the home page.
 */
final readonly class PagePath implements Stringable
{
    private const int MAX_LENGTH = 200;

    private const string SEGMENT_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /** First segments that would collide with directories owned by the system. */
    private const array RESERVED_FIRST_SEGMENTS = ['admin', 'api', 'assets', 'data'];

    public string $value;

    public function __construct(string $value)
    {
        if ($value !== '') {
            $this->assertValid($value);
        }

        $this->value = $value;
    }

    #[Override]
    public function __toString(): string
    {
        return $this->value;
    }

    public static function home(): self
    {
        return new self('');
    }

    public function isHome(): bool
    {
        return $this->value === '';
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    private function assertValid(string $value): void
    {
        if (strlen($value) > self::MAX_LENGTH) {
            throw new InvalidArgumentException(sprintf('Page path is longer than %d characters.', self::MAX_LENGTH));
        }

        $segments = explode('/', $value);
        foreach ($segments as $segment) {
            if (preg_match(self::SEGMENT_PATTERN, $segment) !== 1) {
                throw new InvalidArgumentException(
                    sprintf('Page path "%s" must consist of lowercase latin letters, digits and hyphens, separated by "/".', $value),
                );
            }
        }

        if (in_array($segments[0], self::RESERVED_FIRST_SEGMENTS, true)) {
            throw new InvalidArgumentException(sprintf('Page path "%s" starts with a reserved name.', $value));
        }
    }
}
