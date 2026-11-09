<?php

declare(strict_types=1);

namespace Yef\Domain\Module\Exception;

use RuntimeException;
use Yef\Domain\Module\ModuleName;

use function sprintf;

final class ModuleCannotBeEnabled extends RuntimeException
{
    public static function because(ModuleName $name, string $reason): self
    {
        return new self(sprintf('Module "%s" cannot be enabled: %s', $name, $reason));
    }
}
