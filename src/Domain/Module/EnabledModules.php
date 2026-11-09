<?php

declare(strict_types=1);

namespace Yef\Domain\Module;

use Yef\Domain\Module\Exception\ModuleSettingsFailed;

/**
 * Which modules the site owner has switched on. Unreadable settings read as "none".
 */
interface EnabledModules
{
    /**
     * @return list<ModuleName>
     */
    public function all(): array;

    /**
     * Does nothing when the module is already enabled.
     *
     * @throws ModuleSettingsFailed
     */
    public function enable(ModuleName $name): void;

    /**
     * Does nothing when the module is not enabled.
     *
     * @throws ModuleSettingsFailed
     */
    public function disable(ModuleName $name): void;
}
