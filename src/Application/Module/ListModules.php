<?php

declare(strict_types=1);

namespace Yef\Application\Module;

use Yef\Domain\Module\ModuleCatalog;
use Yef\Domain\Module\ModuleStatus;

/**
 * The installed modules with their titles and states, for screens and the console.
 *
 * @psalm-api Used by the admin and console adapters.
 */
final readonly class ListModules
{
    public function __construct(private ModuleCatalog $catalog) {}

    /**
     * @return list<ModuleStatus>
     */
    public function handle(): array
    {
        return $this->catalog->statuses();
    }
}
