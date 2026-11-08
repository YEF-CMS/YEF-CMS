<?php

declare(strict_types=1);

use Yef\Console;

return [
    'yef:check' => Console\CheckEnvironmentCommand::class,
    'yef:storage:check' => Console\CheckPageStorageCommand::class,
];
