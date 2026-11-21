<?php

declare(strict_types=1);

namespace Yef\Domain\Module\Install;

use Yef\Domain\Module\ModuleName;

/**
 * What a module's manifest says, checked, together with whether the module fits this version of the core.
 *
 * @psalm-api Read by the templates of the admin.
 */
final readonly class ModuleInfo
{
    /**
     * @param string $title The title in English, as the manifest has it.
     * @param string $namespace The PHP namespace of the module, with the backslash at the end.
     * @param string $coreConstraint The versions of the core the module works with.
     */
    public function __construct(
        public ModuleName $name,
        public string $title,
        public string $description,
        public string $version,
        public string $namespace,
        public string $coreConstraint,
        public bool $fitsCore,
    ) {}
}
