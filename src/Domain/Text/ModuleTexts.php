<?php

declare(strict_types=1);

namespace Yef\Domain\Text;

use Yef\Domain\Module\ModuleName;

/**
 * Finds the texts a module brings along. They are available for every installed module, enabled or not,
 * because the list of modules shows the title of a module that is not running.
 */
interface ModuleTexts
{
    public function of(ModuleName $module): Texts;
}
