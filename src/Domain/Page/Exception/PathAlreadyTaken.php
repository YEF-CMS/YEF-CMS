<?php

declare(strict_types=1);

namespace Yef\Domain\Page\Exception;

use DomainException;
use Yef\Domain\Page\PagePath;

use function sprintf;

final class PathAlreadyTaken extends DomainException
{
    public static function byPath(PagePath $path): self
    {
        return new self(sprintf('Another page already uses the path "%s".', $path));
    }
}
