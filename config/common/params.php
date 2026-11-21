<?php

declare(strict_types=1);

return [
    'yiisoft/aliases' => [
        'aliases' => require __DIR__ . '/aliases.php',
    ],
    'yef' => [
        'admin' => [
            // The shortest password a user may choose.
            'minPasswordLength' => 6,
            // How long an inactive admin session stays valid, in seconds.
            'sessionIdleTimeout' => 7200,
            // Sign-in and setup attempts one address may make in a row, and the period over which they come back
            // (after the burst, one more attempt is allowed every period / attempts seconds).
            'attempts' => 5,
            'attemptPeriod' => 900,
            // Set to true on a host without URL rewriting: links then always contain index.php (/admin/index.php/login).
            'scriptNameInUrls' => false,
            // The languages of the admin, by code, with their names in the language itself. The owner picks one
            // for the whole site in the admin; the texts of a language are in src/Admin/messages/<code>/admin.php.
            // Until one is picked, 'yiisoft/translator' => 'locale' below is used.
            'languages' => [
                'en' => 'English',
                'ru' => 'Русский',
                'de' => 'Deutsch',
                'fr' => 'Français',
                'it' => 'Italiano',
            ],
        ],
    ],
    'yiisoft/translator' => [
        'locale' => 'en',
        'fallbackLocale' => 'en',
        'defaultCategory' => 'app',
    ],
];
