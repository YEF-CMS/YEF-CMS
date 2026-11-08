<?php

declare(strict_types=1);

namespace Yef\Domain\Page\Exception;

use RuntimeException;
use Throwable;
use Yef\Domain\Page\PageId;

use function sprintf;

/**
 * The stored data of a page cannot be turned back into a page.
 */
final class PageDataCorrupted extends RuntimeException
{
    public static function forPage(PageId $id, ?Throwable $previous = null): self
    {
        return new self(sprintf('Stored data of page "%s" is corrupted.', $id), 0, $previous);
    }
}
