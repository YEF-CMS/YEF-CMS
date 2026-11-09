<?php

declare(strict_types=1);

namespace Yef\Storage;

use InvalidArgumentException;
use JsonException;
use Yef\Domain\Page\ContentVersion;

use function is_array;
use function is_file;
use function is_string;
use function json_decode;
use function json_encode;

/**
 * A derived copy of every page's metadata and content version in one file, so lists, path lookups and
 * version checks need no directory scan. It can always be rebuilt from the pages themselves.
 */
final readonly class PageIndex
{
    private const string VERSION_FIELD = 'content_version';

    public function __construct(
        private string $path,
        private Files $files,
        private PageMetadataRecord $records,
    ) {}

    /**
     * @return list<IndexedPage>|null Null when the index is missing or unreadable and has to be rebuilt.
     */
    public function load(): ?array
    {
        if (!is_file($this->path)) {
            return null;
        }

        try {
            /** @psalm-suppress MixedAssignment The shape is checked right below. */
            $decoded = json_decode($this->files->read($this->path), true, flags: \JSON_THROW_ON_ERROR);
            $records = is_array($decoded) ? ($decoded['pages'] ?? null) : null;
            if (!is_array($records)) {
                return null;
            }

            $pages = [];
            /** @psalm-suppress MixedAssignment Records are validated one by one. */
            foreach ($records as $record) {
                $pages[] = new IndexedPage($this->records->fromRecord($record), $this->versionOf($record));
            }

            return $pages;
        } catch (JsonException|InvalidArgumentException|StorageIoFailure) {
            return null;
        }
    }

    /**
     * @param list<IndexedPage> $pages
     *
     * @throws StorageIoFailure
     */
    public function store(array $pages): void
    {
        $records = [];
        foreach ($pages as $page) {
            $records[] = [...$this->records->toRecord($page->metadata), self::VERSION_FIELD => $page->version->value];
        }

        $this->files->writeAtomically(
            $this->path,
            json_encode(
                ['version' => 2, 'pages' => $records],
                \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE,
            ),
        );
    }

    /**
     * @throws StorageIoFailure
     */
    public function discard(): void
    {
        $this->files->remove($this->path);
    }

    /**
     * @throws InvalidArgumentException
     */
    private function versionOf(mixed $record): ContentVersion
    {
        /** @psalm-suppress MixedAssignment The type is checked right below. */
        $version = is_array($record) ? ($record[self::VERSION_FIELD] ?? null) : null;

        return is_string($version) ? new ContentVersion($version) : throw new InvalidArgumentException('Index entry has no content version.');
    }
}
