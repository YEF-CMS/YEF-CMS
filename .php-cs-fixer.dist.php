<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = (new Finder())
    ->in([
        __DIR__ . '/config',
        __DIR__ . '/public',
        __DIR__ . '/src',
        __DIR__ . '/modules',
        __DIR__ . '/tests',
    ])
    ->exclude(['.merge-plan.php'])
    ->append([__DIR__ . '/bin/yef']);

return (new Config())
    ->setRiskyAllowed(true)
    ->setCacheFile(__DIR__ . '/var/.php-cs-fixer.cache')
    ->setRules([
        '@PER-CS' => true,
        '@PER-CS:risky' => true,
        '@PHP84Migration' => true,
        '@PHP82Migration:risky' => true,
        '@PHPUnit100Migration:risky' => true,
        'declare_strict_types' => true,
        'strict_comparison' => true,
        'strict_param' => true,
        'final_class' => true,
        'void_return' => true,
        'native_function_invocation' => false,
        'global_namespace_import' => ['import_classes' => true, 'import_functions' => true, 'import_constants' => false],
        'no_unused_imports' => true,
        'ordered_imports' => ['imports_order' => ['class', 'function', 'const'], 'sort_algorithm' => 'alpha'],
        'ordered_class_elements' => true,
        'no_superfluous_phpdoc_tags' => ['allow_mixed' => true, 'remove_inheritdoc' => true],
        'phpdoc_trim' => true,
        'phpdoc_no_empty_return' => true,
        'phpdoc_scalar' => true,
        'phpdoc_types' => true,
        'phpdoc_align' => ['align' => 'left'],
        'no_useless_else' => true,
        'no_useless_return' => true,
        'single_quote' => true,
        'trailing_comma_in_multiline' => ['elements' => ['arguments', 'arrays', 'match', 'parameters']],
        'yoda_style' => false,
    ])
    ->setFinder($finder);
