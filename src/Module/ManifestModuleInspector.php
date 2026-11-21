<?php

declare(strict_types=1);

namespace Yef\Module;

use Override;
use Yef\Domain\Module\Install\Exception\InvalidModule;
use Yef\Domain\Module\Install\ModuleInfo;
use Yef\Domain\Module\Install\ModuleInspector;

use function is_dir;
use function scandir;
use function sort;
use function str_starts_with;

/**
 * Reads the manifests of modules with the same reader the loader uses, and judges them against this core.
 *
 * @psalm-api Wired in through the DI configuration.
 */
final readonly class ManifestModuleInspector implements ModuleInspector
{
    public function __construct(
        private ManifestReader $manifests,
        private string $modulesDirectory,
        private string $coreVersion,
    ) {}

    #[Override]
    public function inspect(string $directory): ModuleInfo
    {
        try {
            return $this->describe($this->manifests->readUnplaced($directory));
        } catch (InvalidManifest $invalidManifest) {
            throw new InvalidModule($invalidManifest->getMessage(), 0, $invalidManifest);
        }
    }

    /**
     * @return list<ModuleInfo>
     */
    #[Override]
    public function installed(): array
    {
        $names = is_dir($this->modulesDirectory) ? scandir($this->modulesDirectory) : false;
        $directories = [];
        foreach ($names === false ? [] : $names as $name) {
            $path = $this->modulesDirectory . '/' . $name;
            if (!str_starts_with($name, '.') && is_dir($path)) {
                $directories[] = $path;
            }
        }

        sort($directories);

        $modules = [];
        foreach ($directories as $directory) {
            try {
                $modules[] = $this->describe($this->manifests->read($directory));
            } catch (InvalidManifest) {
                // A module with a broken manifest is shown in the list of modules as failed; here it is just not counted.
                continue;
            }
        }

        return $modules;
    }

    private function describe(ModuleManifest $manifest): ModuleInfo
    {
        return new ModuleInfo(
            $manifest->name,
            $manifest->title,
            $manifest->description,
            $manifest->version,
            $manifest->namespace,
            $manifest->coreConstraint,
            $manifest->supports($this->coreVersion),
        );
    }
}
