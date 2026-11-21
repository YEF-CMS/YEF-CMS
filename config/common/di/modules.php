<?php

declare(strict_types=1);

use Psr\Log\LoggerInterface;
use Yef\Domain\Module\EnabledModules;
use Yef\Domain\Module\ModuleCatalog;
use Yef\Domain\Text\ModuleTexts;
use Yef\Domain\Text\Texts;
use Yef\Module\ManifestReader;
use Yef\Module\ModuleAutoloader;
use Yef\Module\ModuleLoader;
use Yef\Storage\FileEnabledModules;
use Yiisoft\Aliases\Aliases;

// `Texts` and `ModuleTexts` are defined per entry point: the admin uses the language the owner chose,
// the console always uses English (see config/web/di/texts.php and config/console/di/texts.php).
return [
    EnabledModules::class => static fn(Aliases $aliases): FileEnabledModules => new FileEnabledModules(
        $aliases->get('@config') . '/modules.json',
        $aliases->get('@runtime') . '/storage/modules.lock',
    ),
    ModuleLoader::class => static fn(
        Aliases $aliases,
        EnabledModules $enabledModules,
        LoggerInterface $logger,
        Texts $coreTexts,
        ModuleTexts $moduleTexts,
    ): ModuleLoader => new ModuleLoader(
        $aliases->get('@modules'),
        $enabledModules,
        new ManifestReader(),
        new ModuleAutoloader(),
        $logger,
        $coreTexts,
        $moduleTexts,
    ),
    ModuleCatalog::class => ModuleLoader::class,
];
