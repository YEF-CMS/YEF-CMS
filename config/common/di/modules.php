<?php

declare(strict_types=1);

use Psr\Log\LoggerInterface;
use Yef\Domain\Module\EnabledModules;
use Yef\Domain\Module\ModuleCatalog;
use Yef\Module\ManifestReader;
use Yef\Module\ModuleAutoloader;
use Yef\Module\ModuleLoader;
use Yef\Storage\FileEnabledModules;
use Yiisoft\Aliases\Aliases;

return [
    EnabledModules::class => static fn(Aliases $aliases): FileEnabledModules => new FileEnabledModules(
        $aliases->get('@config') . '/modules.json',
        $aliases->get('@runtime') . '/storage/modules.lock',
    ),
    ModuleLoader::class => static fn(
        Aliases $aliases,
        EnabledModules $enabledModules,
        LoggerInterface $logger,
    ): ModuleLoader => new ModuleLoader(
        $aliases->get('@modules'),
        $enabledModules,
        new ManifestReader(),
        new ModuleAutoloader(),
        $logger,
    ),
    ModuleCatalog::class => ModuleLoader::class,
];
