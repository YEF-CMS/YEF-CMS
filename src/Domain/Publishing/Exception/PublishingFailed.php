<?php

declare(strict_types=1);

namespace Yef\Domain\Publishing\Exception;

use RuntimeException;
use Throwable;
use Yef\Domain\Publishing\PublicPath;

use function sprintf;

/**
 * An artifact could not be published or removed. The cause stays in `previous`.
 */
final class PublishingFailed extends RuntimeException
{
    public static function whilePublishing(PublicPath $path, ?Throwable $previous = null): self
    {
        return new self(sprintf('Could not publish "%s".', $path), 0, $previous);
    }

    public static function whileRecording(?Throwable $previous = null): self
    {
        return new self('Could not update the record of published pages.', 0, $previous);
    }

    public static function whileRemoving(PublicPath $path, ?Throwable $previous = null): self
    {
        return new self(sprintf('Could not remove "%s".', $path), 0, $previous);
    }
}
