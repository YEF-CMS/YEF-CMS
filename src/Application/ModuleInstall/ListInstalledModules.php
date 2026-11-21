<?php

declare(strict_types=1);

namespace Yef\Application\ModuleInstall;

use Yef\Domain\Module\Install\InstalledModule;
use Yef\Domain\Module\Install\InstalledModules;

/**
 * The modules the installer brought, with where they came from.
 *
 * @psalm-api Used by the admin and console adapters.
 */
final readonly class ListInstalledModules
{
    public function __construct(private InstalledModules $installed) {}

    /**
     * @return list<InstalledModule>
     */
    public function handle(): array
    {
        return $this->installed->all();
    }
}
