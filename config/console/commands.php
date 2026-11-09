<?php

declare(strict_types=1);

use Yef\Console;

return [
    'yef:check' => Console\CheckEnvironmentCommand::class,
    'yef:storage:check' => Console\CheckPageStorageCommand::class,
    'yef:publish' => Console\PublishPageCommand::class,
    'yef:rebuild' => Console\RebuildSiteCommand::class,
    'yef:apply' => Console\ApplyChangesCommand::class,
    'yef:modules' => Console\ListModulesCommand::class,
    'yef:module:enable' => Console\EnableModuleCommand::class,
    'yef:module:disable' => Console\DisableModuleCommand::class,
];
