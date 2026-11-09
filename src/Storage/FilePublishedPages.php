<?php

declare(strict_types=1);

namespace Yef\Storage;

use InvalidArgumentException;
use JsonException;
use Override;
use Yef\Domain\Page\ContentVersion;
use Yef\Domain\Page\PageId;
use Yef\Domain\Page\PagePath;
use Yef\Domain\Publishing\Exception\PublishingFailed;
use Yef\Domain\Publishing\PublishedPage;
use Yef\Domain\Publishing\PublishedPages;

use function array_values;
use function is_array;
use function is_file;
use function is_string;
use function json_decode;
use function json_encode;

/**
 * Keeps the record of published pages in one file. A missing or unreadable file reads as an empty record,
 * which makes the next apply build every page again; nothing is lost that cannot be rebuilt.
 *
 * @psalm-api Wired in through the DI configuration.
 */
final readonly class FilePublishedPages implements PublishedPages
{
    private Files $files;

    private FileLock $lock;

    public function __construct(private StorageLayout $layout)
    {
        $this->files = new Files();
        $this->lock = new FileLock($layout->publishedLockFile());
    }

    #[Override]
    public function all(): array
    {
        return array_values($this->load());
    }

    #[Override]
    public function find(PageId $id): ?PublishedPage
    {
        return $this->load()[$id->value] ?? null;
    }

    #[Override]
    public function record(PublishedPage $page): void
    {
        $this->change(static function (array $pages) use ($page): array {
            $pages[$page->id->value] = $page;

            return $pages;
        });
    }

    #[Override]
    public function forget(PageId $id): void
    {
        $this->change(static function (array $pages) use ($id): array {
            unset($pages[$id->value]);

            return $pages;
        });
    }

    /**
     * @param callable(array<string, PublishedPage>): array<string, PublishedPage> $change
     *
     * @throws PublishingFailed
     */
    private function change(callable $change): void
    {
        try {
            $this->files->ensureDirectory($this->layout->stateDirectory);
            $this->lock->exclusive(fn() => $this->store($change($this->load())));
        } catch (StorageIoFailure|JsonException $failure) {
            throw PublishingFailed::whileRecording($failure);
        }
    }

    /**
     * @return array<string, PublishedPage> Page id => its entry.
     */
    private function load(): array
    {
        $path = $this->layout->publishedFile();
        if (!is_file($path)) {
            return [];
        }

        try {
            /** @psalm-suppress MixedAssignment The shape is checked right below. */
            $decoded = json_decode($this->files->read($path), true, flags: \JSON_THROW_ON_ERROR);
            $records = is_array($decoded) ? ($decoded['pages'] ?? null) : null;
            if (!is_array($records)) {
                return [];
            }

            $pages = [];
            /** @psalm-suppress MixedAssignment Records are validated one by one. */
            foreach ($records as $record) {
                $page = $this->fromRecord($record);
                $pages[$page->id->value] = $page;
            }

            return $pages;
        } catch (JsonException|InvalidArgumentException|StorageIoFailure) {
            return [];
        }
    }

    /**
     * @param array<string, PublishedPage> $pages
     *
     * @throws StorageIoFailure
     * @throws JsonException
     */
    private function store(array $pages): void
    {
        $records = [];
        foreach ($pages as $page) {
            $records[] = [
                'id' => $page->id->value,
                'path' => $page->path->value,
                'version' => $page->version->value,
            ];
        }

        $this->files->writeAtomically(
            $this->layout->publishedFile(),
            json_encode(
                ['version' => 1, 'pages' => $records],
                \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE,
            ),
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    private function fromRecord(mixed $record): PublishedPage
    {
        if (!is_array($record)) {
            throw new InvalidArgumentException('A record of a published page must be an array.');
        }

        return new PublishedPage(
            new PageId($this->text($record, 'id')),
            new PagePath($this->text($record, 'path')),
            new ContentVersion($this->text($record, 'version')),
        );
    }

    /**
     * @param array<array-key, mixed> $record
     */
    private function text(array $record, string $field): string
    {
        /** @psalm-suppress MixedAssignment The type is checked right below. */
        $value = $record[$field] ?? null;

        return is_string($value) ? $value : throw new InvalidArgumentException('Published page field "' . $field . '" is missing.');
    }
}
