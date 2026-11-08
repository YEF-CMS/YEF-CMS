<?php

declare(strict_types=1);

use Yef\Domain\ContentType\ContentTypeRegistry;
use Yef\Domain\Page\PageIdGenerator;
use Yef\Domain\Page\PageRepository;
use Yef\Domain\Page\PageStorageIntegrity;
use Yef\Storage\FilePageIntegrity;
use Yef\Storage\FilePageRepository;
use Yef\Storage\StorageLayout;
use Yef\Storage\UlidPageIdGenerator;
use Yiisoft\Aliases\Aliases;

return [
    StorageLayout::class => static fn(Aliases $aliases): StorageLayout => new StorageLayout(
        $aliases->get('@content') . '/pages',
        $aliases->get('@runtime') . '/storage',
    ),
    // No content types are registered until the module loader exists; pages then read as unavailable.
    ContentTypeRegistry::class => [
        '__construct()' => [[]],
    ],
    PageRepository::class => FilePageRepository::class,
    PageStorageIntegrity::class => FilePageIntegrity::class,
    PageIdGenerator::class => UlidPageIdGenerator::class,
];
