<?php

declare(strict_types=1);

namespace Yef\Tests\Support;

use Override;
use Yef\Domain\Module\Install\InstalledModule;
use Yef\Domain\Module\Install\InstalledModules;
use Yef\Domain\Module\ModuleName;

final class InMemoryInstalledModules implements InstalledModules
{
    /** @var array<string, InstalledModule> */
    private array $modules = [];

    #[Override]
    public function all(): array
    {
        $modules = array_values($this->modules);
        usort($modules, static fn(InstalledModule $a, InstalledModule $b): int => $a->name->value <=> $b->name->value);

        return $modules;
    }

    #[Override]
    public function find(ModuleName $module): ?InstalledModule
    {
        return $this->modules[$module->value] ?? null;
    }

    #[Override]
    public function record(InstalledModule $module): void
    {
        $this->modules[$module->name->value] = $module;
    }

    #[Override]
    public function forget(ModuleName $module): void
    {
        unset($this->modules[$module->value]);
    }
}
