<?php

declare(strict_types=1);

namespace Yef\Storage;

use DirectoryIterator;
use InvalidArgumentException;
use JsonException;
use Yef\Domain\Page\PageId;
use Yef\Domain\Page\PageMetadata;

use function is_dir;
use function json_decode;
use function usort;

/**
 * Reads pages straight from their directories, without the index.
 */
final readonly class PageScanner
{
    public function __construct(
        private StorageLayout $layout,
        private Files $files,
        private PageMetadataRecord $records,
    ) {}

    /**
     * @throws StorageIoFailure
     * @throws JsonException
     * @throws InvalidArgumentException
     */
    public function readMetadata(string $pageDirectory): PageMetadata
    {
        return $this->records->fromRecord(
            json_decode($this->files->read($this->layout->metadataFile($pageDirectory)), true, flags: \JSON_THROW_ON_ERROR),
        );
    }

    /**
     * Directories that are not named like a page (leftovers of interrupted writes) are not pages and are ignored.
     */
    public function scan(): PageScan
    {
        $pages = [];
        $damaged = [];
        foreach ($this->pageDirectories() as $id => $directory) {
            $metadata = $this->tryReadMetadata($directory);
            if ($metadata instanceof PageMetadata && $metadata->id->value === $id) {
                $pages[] = $metadata;
            } else {
                $damaged[] = new PageId($id);
            }
        }

        usort($pages, static fn(PageMetadata $first, PageMetadata $second): int => $first->id->value <=> $second->id->value);
        usort($damaged, static fn(PageId $first, PageId $second): int => $first->value <=> $second->value);

        return new PageScan($pages, $damaged);
    }

    /**
     * @return array<string, string> Page id => its directory.
     */
    private function pageDirectories(): array
    {
        $directories = [];
        if (!is_dir($this->layout->pagesDirectory)) {
            return $directories;
        }

        foreach (new DirectoryIterator($this->layout->pagesDirectory) as $entry) {
            if ($entry->isDir() && !$entry->isDot() && $this->layout->pageIdOfDirectoryName($entry->getFilename()) instanceof PageId) {
                $directories[$entry->getFilename()] = $entry->getPathname();
            }
        }

        return $directories;
    }

    private function tryReadMetadata(string $directory): ?PageMetadata
    {
        try {
            return $this->readMetadata($directory);
        } catch (StorageIoFailure|JsonException|InvalidArgumentException) {
            return null;
        }
    }
}
