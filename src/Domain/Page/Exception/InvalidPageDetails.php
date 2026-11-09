<?php

declare(strict_types=1);

namespace Yef\Domain\Page\Exception;

use InvalidArgumentException;

use function sprintf;

/**
 * The address or the title of a page is not acceptable. `field` and `rule` let screens point at the field and say
 * what is wrong in the user's language; the message says the same in English for the console and logs.
 */
final class InvalidPageDetails extends InvalidArgumentException
{
    public const string PATH = 'path';

    public const string TITLE = 'title';

    public const string EMPTY = 'empty';

    public const string TOO_LONG = 'too_long';

    public const string BAD_SEGMENT = 'bad_segment';

    public const string RESERVED = 'reserved';

    private function __construct(
        public readonly string $field,
        public readonly string $rule,
        public readonly int $limit,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function pathTooLong(int $maxLength): self
    {
        return new self(self::PATH, self::TOO_LONG, $maxLength, sprintf('Page path is longer than %d characters.', $maxLength));
    }

    public static function pathHasBadSegment(string $path): self
    {
        return new self(
            self::PATH,
            self::BAD_SEGMENT,
            0,
            sprintf('Page path "%s" must consist of lowercase latin letters, digits and hyphens, separated by "/".', $path),
        );
    }

    public static function pathIsReserved(string $path): self
    {
        return new self(self::PATH, self::RESERVED, 0, sprintf('Page path "%s" starts with a reserved name.', $path));
    }

    public static function titleIsEmpty(): self
    {
        return new self(self::TITLE, self::EMPTY, 0, 'Page title must not be empty.');
    }
}
