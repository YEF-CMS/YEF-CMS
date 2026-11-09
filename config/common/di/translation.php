<?php

declare(strict_types=1);

use Yiisoft\Aliases\Aliases;
use Yiisoft\Translator\CategorySource;
use Yiisoft\Translator\Message\Php\MessageSource;
use Yiisoft\Translator\SimpleMessageFormatter;

return [
    // Texts of the admin: src/Admin/messages/<locale>/admin.php
    'translation.admin' => [
        'definition' => static fn(Aliases $aliases): CategorySource => new CategorySource(
            'admin',
            new MessageSource($aliases->get('@src/Admin/messages')),
            new SimpleMessageFormatter(),
        ),
        'tags' => ['translation.categorySource'],
    ],
];
