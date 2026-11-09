<?php

declare(strict_types=1);

namespace Yef\Domain\Module;

/**
 * The modules found in the installation and their state.
 */
interface ModuleCatalog
{
    /**
     * @return list<ModuleStatus> Ordered by module directory.
     */
    public function statuses(): array;
}
