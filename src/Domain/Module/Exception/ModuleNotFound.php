<?php

declare(strict_types=1);

namespace Yef\Domain\Module\Exception;

use RuntimeException;
use Yef\Domain\Module\ModuleName;

use function sprintf;

final class ModuleNotFound extends RuntimeException
{
    public static function withName(ModuleName $name): self
    {
        return new self(sprintf('Module "%s" is not installed.', $name));
    }
}
