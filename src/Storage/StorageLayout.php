<?php

declare(strict_types=1);

namespace Yef\Storage;

use InvalidArgumentException;
use Yef\Domain\Page\PageId;

use function str_ends_with;
use function substr;

/**
 * Where the file storage keeps things. The only place that knows the names of its files and directories.
 */
final readonly class StorageLayout
{
    /**
     * @param string $pagesDirectory Where page directories live.
     * @param string $stateDirectory Where the lock and the index live; both can be recreated.
     */
    public function __construct(
        public string $pagesDirectory,
        public string $stateDirectory,
    ) {}

    public function lockFile(): string
    {
        return $this->stateDirectory . '/pages.lock';
    }

    public function indexFile(): string
    {
        return $this->stateDirectory . '/pages-index.json';
    }

    public function pageDirectory(PageId $id): string
    {
        return $this->pagesDirectory . '/' . $id->value;
    }

    /**
     * @return PageId|null Null when the name is not the name of a page directory.
     */
    public function pageIdOfDirectoryName(string $name): ?PageId
    {
        try {
            return new PageId($name);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    public function metadataFile(string $pageDirectory): string
    {
        return $pageDirectory . '/meta.json';
    }

    public function payloadDirectory(string $pageDirectory): string
    {
        return $pageDirectory . '/payload';
    }

    public function payloadFile(string $pageDirectory, string $part): string
    {
        return $this->payloadDirectory($pageDirectory) . '/' . $part . '.txt';
    }

    /**
     * @return string|null The part name, or null when the file is not a payload part.
     */
    public function partNameOf(string $fileName): ?string
    {
        return str_ends_with($fileName, '.txt') ? substr($fileName, 0, -4) : null;
    }
}
