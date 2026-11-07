<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return (new Configuration())
    ->addPathToScan(__DIR__ . '/src', isDev: false)
    ->addPathToScan(__DIR__ . '/public', isDev: false)
    ->addPathToScan(__DIR__ . '/config', isDev: false)
    ->addPathToScan(__DIR__ . '/tests', isDev: true)
    // Used at runtime without direct class references in scanned code:
    // PHP extensions needed by dependencies, the `bin/yef` entry point (no .php extension),
    // the Yii config plugin and its DI container, and packages wired in via config.
    ->ignoreErrorsOnExtensions(
        ['ext-ctype', 'ext-mbstring'],
        [ErrorType::UNUSED_DEPENDENCY],
    )
    ->ignoreErrorsOnPackages(
        [
            'psr/container',
            'twig/twig',
            'yiisoft/config',
            'yiisoft/di',
            'yiisoft/router-fastroute',
            'yiisoft/yii-console',
            'yiisoft/yii-runner-console',
        ],
        [ErrorType::UNUSED_DEPENDENCY],
    );
