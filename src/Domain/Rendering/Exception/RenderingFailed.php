<?php

declare(strict_types=1);

namespace Yef\Domain\Rendering\Exception;

use RuntimeException;
use Throwable;
use Yef\Domain\Page\PageId;

use function sprintf;

/**
 * The layout could not be applied to a page. The cause stays in `previous`.
 */
final class RenderingFailed extends RuntimeException
{
    public static function forPage(PageId $id, ?Throwable $previous = null): self
    {
        return new self(sprintf('Could not render page "%s".', $id), 0, $previous);
    }
}
