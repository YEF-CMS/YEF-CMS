<?php

declare(strict_types=1);

namespace Yef\Application\ModuleInstall;

use DomainException;
use Yef\Domain\Module\Install\ReleaseVersion;
use Yef\Domain\Module\ModuleName;

use function sprintf;

/**
 * The latest release is not newer than what is installed.
 */
final class ModuleIsUpToDate extends DomainException
{
    public static function named(ModuleName $module, ReleaseVersion $version): self
    {
        return new self(sprintf('The module %s is up to date at %s.', $module, $version));
    }
}
