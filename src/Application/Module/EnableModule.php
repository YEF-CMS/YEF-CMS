<?php

declare(strict_types=1);

namespace Yef\Application\Module;

use InvalidArgumentException;
use Yef\Domain\Module\EnabledModules;
use Yef\Domain\Module\Exception\ModuleCannotBeEnabled;
use Yef\Domain\Module\Exception\ModuleNotFound;
use Yef\Domain\Module\Exception\ModuleSettingsFailed;
use Yef\Domain\Module\ModuleCatalog;
use Yef\Domain\Module\ModuleName;
use Yef\Domain\Module\ModuleState;

/**
 * Switches an installed module on. The module is loaded from the next request on.
 *
 * @psalm-api Used by the admin and console adapters.
 */
final readonly class EnableModule
{
    public function __construct(
        private ModuleCatalog $catalog,
        private EnabledModules $enabledModules,
    ) {}

    /**
     * @throws InvalidArgumentException When the name is not "vendor/name".
     * @throws ModuleNotFound
     * @throws ModuleCannotBeEnabled When the module is broken or incompatible with this version of the core.
     * @throws ModuleSettingsFailed
     */
    public function handle(string $name): void
    {
        $moduleName = new ModuleName($name);

        foreach ($this->catalog->statuses() as $status) {
            if ($status->id !== $moduleName->value) {
                continue;
            }

            if ($status->state === ModuleState::Failed && !$this->isEnabled($moduleName)) {
                throw ModuleCannotBeEnabled::because($moduleName, $status->reason);
            }

            $this->enabledModules->enable($moduleName);

            return;
        }

        throw ModuleNotFound::withName($moduleName);
    }

    private function isEnabled(ModuleName $name): bool
    {
        return array_any($this->enabledModules->all(), static fn(ModuleName $enabled): bool => $enabled->equals($name));
    }
}
