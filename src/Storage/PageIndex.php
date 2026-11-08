<?php

declare(strict_types=1);

namespace Yef\Storage;

use InvalidArgumentException;
use JsonException;
use Yef\Domain\Page\PageMetadata;

use function is_array;
use function is_file;
use function json_decode;
use function json_encode;

/**
 * A derived copy of every page's metadata in one file, so lists and path lookups need no directory scan.
 * It can always be rebuilt from the pages themselves.
 */
final readonly class PageIndex
{
    public function __construct(
        private string $path,
        private Files $files,
        private PageMetadataRecord $records,
    ) {}

    /**
     * @return list<PageMetadata>|null Null when the index is missing or unreadable and has to be rebuilt.
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
                $pages[] = $this->records->fromRecord($record);
            }

            return $pages;
        } catch (JsonException|InvalidArgumentException|StorageIoFailure) {
            return null;
        }
    }

    /**
     * @param list<PageMetadata> $pages
     *
     * @throws StorageIoFailure
     */
    public function store(array $pages): void
    {
        $records = [];
        foreach ($pages as $metadata) {
            $records[] = $this->records->toRecord($metadata);
        }

        $this->files->writeAtomically(
            $this->path,
            json_encode(
                ['version' => 1, 'pages' => $records],
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
}
