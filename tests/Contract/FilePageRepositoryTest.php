<?php

declare(strict_types=1);

namespace Yef\Tests\Contract;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Yef\Storage\FileLock;
use Yef\Storage\FilePageRepository;
use Yef\Storage\Files;
use Yef\Storage\PageIndex;
use Yef\Storage\PageMetadataRecord;
use Yef\Storage\StorageIoFailure;
use Yef\Storage\StorageLayout;
use Yef\Tests\Support\TemporaryDirectory;
use YefCms\TextPages\TextContentType;

#[CoversClass(FilePageRepository::class)]
#[CoversClass(Files::class)]
#[CoversClass(FileLock::class)]
#[CoversClass(PageIndex::class)]
#[CoversClass(PageMetadataRecord::class)]
#[CoversClass(StorageIoFailure::class)]
final class FilePageRepositoryTest extends PageRepositoryContract
{
    private TemporaryDirectory $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = new TemporaryDirectory();
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->directory->delete();
    }

    #[Override]
    protected function createRepository(): FilePageRepository
    {
        return new FilePageRepository(
            new StorageLayout($this->directory->path . '/content/pages', $this->directory->path . '/var/storage'),
            TextContentType::registry(),
        );
    }
}
