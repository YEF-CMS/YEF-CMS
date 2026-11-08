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
use YefCms\TextPages\TextContentType;
use Yiisoft\Aliases\Aliases;

return [
    StorageLayout::class => static fn(Aliases $aliases): StorageLayout => new StorageLayout(
        $aliases->get('@content') . '/pages',
        $aliases->get('@runtime') . '/storage',
    ),
    // Until the module loader exists, the bundled text module is registered by hand;
    // pages of any other type read as unavailable.
    ContentTypeRegistry::class => static fn(): ContentTypeRegistry => new ContentTypeRegistry([
        TextContentType::definition(),
    ]),
    PageRepository::class => FilePageRepository::class,
    PageStorageIntegrity::class => FilePageIntegrity::class,
    PageIdGenerator::class => UlidPageIdGenerator::class,
];
