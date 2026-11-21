<?php

declare(strict_types=1);

namespace Yef\Admin\Modules;

use InvalidArgumentException;
use Yef\Domain\Module\ModuleName;
use Yef\Domain\Module\ModuleStatus;

/**
 * A module as the list shows it: its status and the address part that the switch buttons use.
 *
 * @psalm-api Read by the template modules/index.twig.
 */
final readonly class ModuleRow
{
    /**
     * @param ?string $slug Null when the module has no usable name (its manifest is unreadable), so it cannot be switched.
     */
    private function __construct(
        public ModuleStatus $status,
        public ?string $slug,
    ) {}

    public static function of(ModuleStatus $status): self
    {
        try {
            return new self($status, new ModuleName($status->id)->directoryName());
        } catch (InvalidArgumentException) {
            return new self($status, null);
        }
    }
}
