<?php

declare(strict_types=1);

namespace Yef\Domain\Page\Exception;

use DomainException;
use Yef\Domain\Page\PageId;

use function sprintf;

final class PageNotFound extends DomainException
{
    public static function withId(PageId $id): self
    {
        return new self(sprintf('Page "%s" does not exist.', $id));
    }
}
