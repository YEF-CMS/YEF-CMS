<?php

declare(strict_types=1);

namespace Yef\Domain\Module\Install\Exception;

use DomainException;

use function sprintf;

/**
 * The archive is not a zip file, or would do something an archive of a module must not do.
 */
final class UnsafeArchive extends DomainException
{
    public const string NOT_AN_ARCHIVE = 'not_an_archive';

    public const string TOO_MANY_FILES = 'too_many_files';

    public const string TOO_LARGE = 'too_large';

    public const string BAD_PATH = 'bad_path';

    public const string LINK = 'link';

    public const string EMPTY = 'empty';

    public const string WRITE_FAILED = 'write_failed';

    private function __construct(
        public readonly string $reason,
        string $detail,
    ) {
        parent::__construct(sprintf('The archive of the module cannot be used (%s)%s', $reason, $detail === '' ? '.' : ': ' . $detail));
    }

    /**
     * @param self::NOT_AN_ARCHIVE|self::TOO_MANY_FILES|self::TOO_LARGE|self::BAD_PATH|self::LINK|self::EMPTY|self::WRITE_FAILED $reason
     */
    public static function because(string $reason, string $detail = ''): self
    {
        return new self($reason, $detail);
    }
}
