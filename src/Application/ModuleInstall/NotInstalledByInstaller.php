<?php

declare(strict_types=1);

namespace Yef\Application\ModuleInstall;

use DomainException;
use Yef\Domain\Module\ModuleName;

use function sprintf;

/**
 * The module was not brought by the installer (it came with the CMS or was put into the folder by hand),
 * so the installer knows no source to update it from and does not remove it.
 */
final class NotInstalledByInstaller extends DomainException
{
    public static function named(ModuleName $module): self
    {
        return new self(sprintf('The module %s was not installed by the installer.', $module));
    }
}
