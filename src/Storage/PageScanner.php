<?php

declare(strict_types=1);

namespace Yef\Storage;

use DirectoryIterator;
use InvalidArgumentException;
use JsonException;
use Yef\Domain\ContentType\PayloadData;
use Yef\Domain\Page\ContentVersion;
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
     * @throws StorageIoFailure
     * @throws InvalidArgumentException
     */
    public function readPayload(string $pageDirectory): PayloadData
    {
        $directory = $this->layout->payloadDirectory($pageDirectory);
        $parts = [];
        if (!is_dir($directory)) {
            return new PayloadData($parts);
        }

        foreach (new DirectoryIterator($directory) as $file) {
            $part = $file->isFile() ? $this->layout->partNameOf($file->getFilename()) : null;
            if ($part !== null) {
                $parts[$part] = $this->files->read($file->getPathname());
            }
        }

        return new PayloadData($parts);
    }

    /**
     * Directories that are not named like a page (leftovers of interrupted writes) are not pages and are ignored.
     */
    public function scan(): PageScan
    {
        $pages = [];
        $damaged = [];
        foreach ($this->pageDirectories() as $id => $directory) {
            $page = $this->tryRead($directory);
            if ($page instanceof IndexedPage && $page->metadata->id->value === $id) {
                $pages[] = $page;
            } else {
                $damaged[] = new PageId($id);
            }
        }

        usort($pages, static fn(IndexedPage $first, IndexedPage $second): int => $first->metadata->id->value <=> $second->metadata->id->value);
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

    private function tryRead(string $directory): ?IndexedPage
    {
        try {
            $metadata = $this->readMetadata($directory);

            return new IndexedPage($metadata, ContentVersion::of($metadata, $this->readPayload($directory)));
        } catch (StorageIoFailure|JsonException|InvalidArgumentException) {
            return null;
        }
    }
}
