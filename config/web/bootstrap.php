<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;
use Yiisoft\Aliases\Aliases;

/**
 * @psalm-var list<callable(ContainerInterface): void>
 */
return [
    // PHP does not create the directory for session files by itself.
    static function (ContainerInterface $container): void {
        $directory = $container->get(Aliases::class)->get('@runtime/sessions');
        if (!is_dir($directory)) {
            mkdir($directory, 0o700, true);
        }
    },
];
