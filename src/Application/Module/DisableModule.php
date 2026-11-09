<?php

declare(strict_types=1);

namespace Yef\Application\Module;

use InvalidArgumentException;
use Yef\Domain\Module\EnabledModules;
use Yef\Domain\Module\Exception\ModuleSettingsFailed;
use Yef\Domain\Module\ModuleName;

/**
 * Switches a module off. Pages of its content types stay in the storage and read as unavailable.
 *
 * @psalm-api Used by the admin and console adapters.
 */
final readonly class DisableModule
{
    public function __construct(private EnabledModules $enabledModules) {}

    /**
     * Does nothing when the module is not enabled.
     *
     * @throws InvalidArgumentException When the name is not "vendor/name".
     * @throws ModuleSettingsFailed
     */
    public function handle(string $name): void
    {
        $this->enabledModules->disable(new ModuleName($name));
    }
}
