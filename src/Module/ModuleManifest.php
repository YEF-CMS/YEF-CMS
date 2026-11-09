<?php

declare(strict_types=1);

namespace Yef\Module;

use Composer\Semver\Semver;
use Yef\Domain\Module\ModuleName;

/**
 * A module's `module.json`, checked.
 */
final readonly class ModuleManifest
{
    /**
     * @param string $title What users read in the admin ("Photo gallery").
     * @param string $namespace PSR-4 namespace whose root is the module's `src/`, with the trailing backslash.
     * @param string $entry Full name of the class implementing `ModuleInterface`.
     * @param string $coreConstraint Semver constraint on the core version.
     */
    public function __construct(
        public ModuleName $name,
        public string $title,
        public string $description,
        public string $version,
        public string $namespace,
        public string $entry,
        public string $coreConstraint,
    ) {}

    public function supports(string $coreVersion): bool
    {
        return Semver::satisfies($coreVersion, $this->coreConstraint);
    }
}
