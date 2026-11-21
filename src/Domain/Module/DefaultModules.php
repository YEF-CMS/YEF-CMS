<?php

declare(strict_types=1);

namespace Yef\Domain\Module;

use Yef\Domain\Module\Exception\ModuleSettingsFailed;

/**
 * The modules a site starts with. Used once, when the CMS is installed on a site.
 */
interface DefaultModules
{
    /**
     * Enables the modules the distribution enables from the start. Does nothing when the site already has
     * its own list of enabled modules, so a choice the owner made is never replaced.
     *
     * @throws ModuleSettingsFailed
     */
    public function provide(): void;
}
