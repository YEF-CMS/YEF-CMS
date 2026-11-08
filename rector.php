<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/config',
        __DIR__ . '/public',
        __DIR__ . '/src',
        __DIR__ . '/modules',
        __DIR__ . '/tests',
    ])
    ->withSkip([
        // Generated at install time by yiisoft/config.
        __DIR__ . '/config/.merge-plan.php',
    ])
    ->withCache(__DIR__ . '/var/.rector.cache')
    ->withPhpSets(php84: true)
    ->withComposerBased(phpunit: true)
    ->withAttributesSets(phpunit: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        typeDeclarationDocblocks: true,
        privatization: true,
        instanceOf: true,
        if: true,
        earlyReturn: true,
        phpunitCodeQuality: true,
        phpunitNarrowAsserts: true,
    );
