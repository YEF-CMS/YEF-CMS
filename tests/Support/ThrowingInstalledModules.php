<?php

declare(strict_types=1);

namespace Yef\Tests\Support;

use Override;
use RuntimeException;
use Yef\Domain\Module\Exception\ModuleSettingsFailed;
use Yef\Domain\Module\Install\InstalledModule;
use Yef\Domain\Module\Install\InstalledModules;
use Yef\Domain\Module\ModuleName;

/**
 * A record of installed modules that cannot be written to.
 */
final class ThrowingInstalledModules implements InstalledModules
{
    #[Override]
    public function all(): array
    {
        return [];
    }

    #[Override]
    public function find(ModuleName $module): ?InstalledModule
    {
        return null;
    }

    #[Override]
    public function record(InstalledModule $module): void
    {
        throw ModuleSettingsFailed::whileSaving(new RuntimeException('disk full'));
    }

    #[Override]
    public function forget(ModuleName $module): void {}
}
