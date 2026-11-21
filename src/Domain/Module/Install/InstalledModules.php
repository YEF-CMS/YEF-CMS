<?php

declare(strict_types=1);

namespace Yef\Domain\Module\Install;

use Yef\Domain\Module\Exception\ModuleSettingsFailed;
use Yef\Domain\Module\ModuleName;

/**
 * The modules the installer brought. Modules that were put into the folder by hand, and the ones that come
 * with the CMS, are not on this list: the installer neither updates nor removes them.
 */
interface InstalledModules
{
    /**
     * @return list<InstalledModule> In the order of the module names. A missing or damaged record reads as empty.
     */
    public function all(): array;

    public function find(ModuleName $module): ?InstalledModule;

    /**
     * Records a module, replacing what was recorded about the same module.
     *
     * @throws ModuleSettingsFailed
     */
    public function record(InstalledModule $module): void;

    /**
     * Does nothing when the module is not recorded.
     *
     * @throws ModuleSettingsFailed
     */
    public function forget(ModuleName $module): void;
}
