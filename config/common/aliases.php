<?php

declare(strict_types=1);

return [
    '@root' => dirname(__DIR__, 2),
    '@src' => '@root/src',
    '@public' => '@root/public',
    // Site layout and other templates of the site owner.
    '@templates' => '@root/templates',
    '@vendor' => '@root/vendor',
    // Writable runtime data: caches, logs, locks. Never served directly.
    '@runtime' => '@root/var',
    // Site content: pages and, later, media. The data of the site owner, never part of a release.
    '@content' => '@root/content',
];
