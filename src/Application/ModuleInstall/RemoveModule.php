<?php

declare(strict_types=1);

namespace Yef\Application\ModuleInstall;

use InvalidArgumentException;
use Yef\Domain\Module\EnabledModules;
use Yef\Domain\Module\Exception\ModuleSettingsFailed;
use Yef\Domain\Module\Install\Exception\ModuleFilesFailed;
use Yef\Domain\Module\Install\InstalledModules;
use Yef\Domain\Module\Install\ModuleFiles;
use Yef\Domain\Module\ModuleName;

/**
 * Removes a module that the installer brought: switches it off, deletes its files and forgets where it came from.
 * Pages of its content types stay in the storage and read as unavailable.
 *
 * @psalm-api Used by the admin and console adapters.
 */
final readonly class RemoveModule
{
    public function __construct(
        private InstalledModules $installed,
        private EnabledModules $enabled,
        private ModuleFiles $files,
    ) {}

    /**
     * @throws InvalidArgumentException When the name is not "vendor/name".
     * @throws NotInstalledByInstaller
     * @throws ModuleSettingsFailed
     * @throws ModuleFilesFailed
     */
    public function handle(string $name): void
    {
        $module = new ModuleName($name);
        if (!$this->installed->find($module) instanceof \Yef\Domain\Module\Install\InstalledModule) {
            throw NotInstalledByInstaller::named($module);
        }

        $this->enabled->disable($module);
        $this->files->remove($module);
        $this->installed->forget($module);
    }
}
