<?php

declare(strict_types=1);

namespace Yef\Domain\Module\Install\Exception;

use RuntimeException;
use Throwable;

use function sprintf;

/**
 * The source could not be used: it did not answer, answered something unexpected, or refused (rate limit).
 */
final class SourceUnavailable extends RuntimeException
{
    public const string UNREACHABLE = 'unreachable';

    public const string RATE_LIMITED = 'rate_limited';

    public const string BAD_ANSWER = 'bad_answer';

    public const string TOO_LARGE = 'too_large';

    public const string NO_CLIENT = 'no_client';

    private function __construct(
        public readonly string $reason,
        string $detail,
        ?Throwable $previous,
    ) {
        parent::__construct(sprintf('The source is not available (%s): %s', $reason, $detail), 0, $previous);
    }

    /**
     * @param self::UNREACHABLE|self::RATE_LIMITED|self::BAD_ANSWER|self::TOO_LARGE|self::NO_CLIENT $reason
     */
    public static function because(string $reason, string $detail = '', ?Throwable $previous = null): self
    {
        return new self($reason, $detail, $previous);
    }
}
