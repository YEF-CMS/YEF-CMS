<?php

declare(strict_types=1);

return [
    '@root' => dirname(__DIR__, 2),
    '@src' => '@root/src',
    '@public' => '@root/public',
    '@vendor' => '@root/vendor',
    // Writable runtime data: caches, logs, locks. Never served directly.
    '@runtime' => '@root/var',
];
