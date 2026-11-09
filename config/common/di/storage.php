<?php

declare(strict_types=1);

use Yef\Domain\ContentType\ContentTypeRegistry;
use Yef\Domain\Page\PageIdGenerator;
use Yef\Domain\Page\PageRepository;
use Yef\Domain\Page\PageStorageIntegrity;
use Yef\Domain\Page\PageVersions;
use Yef\Domain\Publishing\PublishedPages;
use Yef\Module\ModuleLoader;
use Yef\Storage\FilePageIntegrity;
use Yef\Storage\FilePageRepository;
use Yef\Storage\FilePublishedPages;
use Yef\Storage\StorageLayout;
use Yef\Storage\UlidPageIdGenerator;
use Yiisoft\Aliases\Aliases;

return [
    StorageLayout::class => static fn(Aliases $aliases): StorageLayout => new StorageLayout(
        $aliases->get('@content') . '/pages',
        $aliases->get('@runtime') . '/storage',
    ),
    // Content types come from the enabled modules; pages of any other type read as unavailable.
    ContentTypeRegistry::class => static fn(ModuleLoader $modules): ContentTypeRegistry => new ContentTypeRegistry(
        $modules->contentTypes(),
    ),
    PageRepository::class => FilePageRepository::class,
    PageVersions::class => FilePageRepository::class,
    PublishedPages::class => FilePublishedPages::class,
    PageStorageIntegrity::class => FilePageIntegrity::class,
    PageIdGenerator::class => UlidPageIdGenerator::class,
];
