<?php

declare(strict_types=1);

namespace Yef\Admin\Modules;

use InvalidArgumentException;
use Yef\Application\ModuleInstall\UpdateCheck;
use Yef\Domain\Module\Install\InstalledModule;
use Yef\Domain\Module\ModuleName;
use Yef\Domain\Module\ModuleStatus;

/**
 * A module as the list shows it: its status, the address part that the buttons use, where the installer got it
 * from (when it did) and what the last check for updates found.
 *
 * @psalm-api Read by the template modules/index.twig.
 */
final readonly class ModuleRow
{
    /**
     * @param ?string $slug Null when the module has no usable name (its manifest is unreadable), so it cannot be switched.
     * @param ?InstalledModule $installed Set when the installer brought the module: only these can be updated or removed.
     * @param ?UpdateCheck $check Set when updates were just checked.
     */
    private function __construct(
        public ModuleStatus $status,
        public ?string $slug,
        public ?InstalledModule $installed,
        public ?UpdateCheck $check,
    ) {}

    public static function of(ModuleStatus $status, ?InstalledModule $installed = null, ?UpdateCheck $check = null): self
    {
        try {
            return new self($status, new ModuleName($status->id)->directoryName(), $installed, $check);
        } catch (InvalidArgumentException) {
            return new self($status, null, null, null);
        }
    }
}
