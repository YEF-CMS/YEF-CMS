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
    'yef.modules' => [
        // Where modules are downloaded from. Only these hosts are contacted, over https; the installer follows
        // redirects itself and refuses any that leads elsewhere. "allowHttp" and another "api" are for testing
        // the installer against a local server.
        'github' => [
            'api' => 'https://api.github.com',
            'hosts' => ['api.github.com', 'codeload.github.com'],
            'allowHttp' => false,
        ],
        'install' => [
            // The archive of a release, in bytes.
            'maxArchiveBytes' => 20 * 1024 * 1024,
            // What the archive may add up to once unpacked, and how many files it may have.
            'maxUnpackedBytes' => 50 * 1024 * 1024,
            'maxFiles' => 2000,
            // Seconds a request to GitHub may take.
            'timeoutSeconds' => 30,
            // Seconds a downloaded module waits for the admin's confirmation before it is thrown away.
            'stagingLifetime' => 3600,
        ],
    ],
    'yiisoft/translator' => [
        'locale' => 'en',
        'fallbackLocale' => 'en',
        'defaultCategory' => 'app',
    ],
];
