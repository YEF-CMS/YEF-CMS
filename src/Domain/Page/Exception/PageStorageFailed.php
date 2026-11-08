<?php

declare(strict_types=1);

namespace Yef\Domain\Page\Exception;

use RuntimeException;
use Throwable;
use Yef\Domain\Page\PageId;

use function sprintf;

/**
 * The storage could not complete an operation. The cause is the storage's business and stays in `previous`.
 */
final class PageStorageFailed extends RuntimeException
{
    public static function forPage(PageId $id, ?Throwable $previous = null): self
    {
        return new self(sprintf('The storage failed while handling page "%s".', $id), 0, $previous);
    }

    public static function whileInspecting(?Throwable $previous = null): self
    {
        return new self('The storage failed while it was being checked.', 0, $previous);
    }

    public static function whileListing(?Throwable $previous = null): self
    {
        return new self('The storage failed while listing pages.', 0, $previous);
    }
}
