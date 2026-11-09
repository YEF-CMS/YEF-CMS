<?php

declare(strict_types=1);

namespace Yef\Storage;

use InvalidArgumentException;
use JsonException;
use Override;
use Throwable;
use Yef\Domain\ContentType\ContentTypeId;
use Yef\Domain\ContentType\ContentTypeRegistry;
use Yef\Domain\ContentType\Exception\InvalidPayload;
use Yef\Domain\ContentType\Payload;
use Yef\Domain\ContentType\PayloadData;
use Yef\Domain\ContentType\UnavailablePayload;
use Yef\Domain\Page\ContentVersion;
use Yef\Domain\Page\Exception\PageDataCorrupted;
use Yef\Domain\Page\Exception\PageNotFound;
use Yef\Domain\Page\Exception\PageStorageFailed;
use Yef\Domain\Page\Exception\PathAlreadyTaken;
use Yef\Domain\Page\Page;
use Yef\Domain\Page\PageId;
use Yef\Domain\Page\PageMetadata;
use Yef\Domain\Page\PagePath;
use Yef\Domain\Page\PageRepository;
use Yef\Domain\Page\PageVersions;

use function array_values;
use function bin2hex;
use function is_dir;
use function is_file;
use function json_encode;
use function random_bytes;

/**
 * Keeps every page in a directory of its own: the metadata and one file per payload part.
 *
 * A page is always replaced as a whole directory, so a reader sees the old page or the new one, never a mix.
 * Writes are serialized by a lock; the index only speeds up lists and path lookups and is rebuilt when lost.
 *
 * @psalm-api Wired in through the DI configuration.
 */
final readonly class FilePageRepository implements PageRepository, PageVersions
{
    private const int JSON_FLAGS = \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE;

    private Files $files;

    private FileLock $lock;

    private PageIndex $index;

    private PageMetadataRecord $records;

    private PageScanner $scanner;

    public function __construct(
        private StorageLayout $layout,
        private ContentTypeRegistry $types,
    ) {
        $this->files = new Files();
        $this->records = new PageMetadataRecord();
        $this->lock = new FileLock($layout->lockFile());
        $this->index = new PageIndex($layout->indexFile(), $this->files, $this->records);
        $this->scanner = new PageScanner($layout, $this->files, $this->records);
    }

    #[Override]
    public function save(Page $page): void
    {
        try {
            $this->exclusively(fn() => $this->saveUnderLock($page));
        } catch (StorageIoFailure|JsonException $failure) {
            throw PageStorageFailed::forPage($page->metadata->id, $failure);
        }
    }

    #[Override]
    public function get(PageId $id): Page
    {
        try {
            return $this->shared(fn(): Page => $this->read($id));
        } catch (StorageIoFailure $storageIoFailure) {
            throw PageStorageFailed::forPage($id, $storageIoFailure);
        }
    }

    #[Override]
    public function findMetadataByPath(PagePath $path): ?PageMetadata
    {
        foreach ($this->listedPages() as $page) {
            if ($page->metadata->details->path->equals($path)) {
                return $page->metadata;
            }
        }

        return null;
    }

    #[Override]
    public function allMetadata(): array
    {
        return array_map(static fn(IndexedPage $page): PageMetadata => $page->metadata, $this->listedPages());
    }

    #[Override]
    public function versionOf(PageId $id): ContentVersion
    {
        foreach ($this->listedPages() as $page) {
            if ($page->metadata->id->equals($id)) {
                return $page->version;
            }
        }

        throw PageNotFound::withId($id);
    }

    #[Override]
    public function all(): array
    {
        $versions = [];
        foreach ($this->listedPages() as $page) {
            $versions[$page->metadata->id->value] = $page->version;
        }

        return $versions;
    }

    #[Override]
    public function delete(PageId $id): void
    {
        try {
            $this->exclusively(fn() => $this->deleteUnderLock($id));
        } catch (StorageIoFailure|JsonException $failure) {
            throw PageStorageFailed::forPage($id, $failure);
        }
    }

    /**
     * @throws StorageIoFailure
     * @throws JsonException
     */
    private function saveUnderLock(Page $page): void
    {
        $metadata = $page->metadata;
        $pages = $this->indexedPages();
        $this->assertPathIsFree($pages, $metadata);

        $data = $this->dataOf($page);
        $directory = $this->layout->pageDirectory($metadata->id);
        $staging = $directory . '.new-' . $this->uniqueSuffix();
        try {
            $this->writePage($staging, $metadata, $data);
            $displaced = $this->replaceDirectory($staging, $directory);
        } catch (Throwable $throwable) {
            $this->removeQuietly($staging);

            throw $throwable;
        }

        $this->storeIndex($this->withEntry($pages, new IndexedPage($metadata, ContentVersion::of($metadata, $data))));
        $this->removeQuietly($displaced);
    }

    /**
     * @throws StorageIoFailure
     * @throws JsonException
     * @throws PageNotFound
     */
    private function deleteUnderLock(PageId $id): void
    {
        $pages = $this->indexedPages();
        $directory = $this->layout->pageDirectory($id);
        if (!is_dir($directory)) {
            throw PageNotFound::withId($id);
        }

        $trash = $directory . '.old-' . $this->uniqueSuffix();
        $this->files->move($directory, $trash);
        $this->storeIndex($this->withoutEntry($pages, $id));
        $this->removeQuietly($trash);
    }

    /**
     * @throws StorageIoFailure
     * @throws PageNotFound
     * @throws PageDataCorrupted
     */
    private function read(PageId $id): Page
    {
        $directory = $this->layout->pageDirectory($id);
        if (!is_file($this->layout->metadataFile($directory))) {
            throw PageNotFound::withId($id);
        }

        try {
            $metadata = $this->scanner->readMetadata($directory);
            if (!$metadata->id->equals($id)) {
                throw new InvalidArgumentException('Stored metadata belongs to another page.');
            }

            return new Page($metadata, $this->payloadOf($metadata->type, $this->scanner->readPayload($directory)));
        } catch (JsonException|InvalidArgumentException|InvalidPayload $failure) {
            throw PageDataCorrupted::forPage($id, $failure);
        }
    }

    private function payloadOf(ContentTypeId $type, PayloadData $data): Payload
    {
        return $this->types->has($type)
            ? $this->types->get($type)->serializer->fromData($data)
            : new UnavailablePayload($type, $data);
    }

    private function dataOf(Page $page): PayloadData
    {
        $payload = $page->payload;

        return $payload instanceof UnavailablePayload
            ? $payload->data
            : $this->types->get($page->metadata->type)->serializer->toData($payload);
    }

    /**
     * @throws StorageIoFailure
     * @throws JsonException
     */
    private function writePage(string $directory, PageMetadata $metadata, PayloadData $data): void
    {
        $this->files->ensureDirectory($this->layout->payloadDirectory($directory));
        foreach ($data->parts() as $name => $contents) {
            $this->files->writeAtomically($this->layout->payloadFile($directory, $name), $contents);
        }

        $this->files->writeAtomically(
            $this->layout->metadataFile($directory),
            json_encode($this->records->toRecord($metadata), self::JSON_FLAGS),
        );
    }

    /**
     * @return string|null The directory of the previous version, to be removed once the new one is indexed.
     *
     * @throws StorageIoFailure
     */
    private function replaceDirectory(string $staging, string $directory): ?string
    {
        if (!is_dir($directory)) {
            $this->files->move($staging, $directory);

            return null;
        }

        $displaced = $directory . '.old-' . $this->uniqueSuffix();
        $this->files->move($directory, $displaced);
        try {
            $this->files->move($staging, $directory);
        } catch (StorageIoFailure $storageIoFailure) {
            $this->files->move($displaced, $directory);

            throw $storageIoFailure;
        }

        return $displaced;
    }

    /**
     * @param list<IndexedPage> $pages
     *
     * @throws PathAlreadyTaken
     */
    private function assertPathIsFree(array $pages, PageMetadata $metadata): void
    {
        foreach ($pages as $other) {
            $otherMetadata = $other->metadata;
            if ($otherMetadata->details->path->equals($metadata->details->path) && !$otherMetadata->id->equals($metadata->id)) {
                throw PathAlreadyTaken::byPath($metadata->details->path);
            }
        }
    }

    /**
     * @param list<IndexedPage> $pages
     *
     * @return list<IndexedPage>
     */
    private function withEntry(array $pages, IndexedPage $entry): array
    {
        $others = $this->withoutEntry($pages, $entry->metadata->id);
        $others[] = $entry;

        return $others;
    }

    /**
     * @param list<IndexedPage> $pages
     *
     * @return list<IndexedPage>
     */
    private function withoutEntry(array $pages, PageId $id): array
    {
        return array_values(array_filter($pages, static fn(IndexedPage $page): bool => !$page->metadata->id->equals($id)));
    }

    /**
     * An index that cannot be written is discarded, so the next reader rebuilds it instead of trusting a stale one.
     *
     * @param list<IndexedPage> $pages
     *
     * @throws StorageIoFailure
     * @throws JsonException
     */
    private function storeIndex(array $pages): void
    {
        try {
            $this->index->store($pages);
        } catch (StorageIoFailure|JsonException $failure) {
            $this->removeIndexQuietly();

            throw $failure;
        }
    }

    /**
     * @return list<IndexedPage>
     */
    private function listedPages(): array
    {
        try {
            return $this->index->load() ?? $this->exclusively($this->indexedPages(...));
        } catch (StorageIoFailure|JsonException $failure) {
            throw PageStorageFailed::whileListing($failure);
        }
    }

    /**
     * Must be called under the lock.
     *
     * @return list<IndexedPage>
     *
     * @throws StorageIoFailure
     * @throws JsonException
     */
    private function indexedPages(): array
    {
        return $this->index->load() ?? $this->rebuildIndex();
    }

    /**
     * Pages whose metadata cannot be read are left out of the index; they stay on disk untouched.
     *
     * @return list<IndexedPage>
     *
     * @throws StorageIoFailure
     * @throws JsonException
     */
    private function rebuildIndex(): array
    {
        $pages = $this->scanner->scan()->pages;
        $this->storeIndex($pages);

        return $pages;
    }

    /**
     * @template T
     *
     * @param callable(): T $action
     *
     * @return T
     *
     * @throws StorageIoFailure
     */
    private function exclusively(callable $action): mixed
    {
        $this->prepareDirectories();

        return $this->lock->exclusive($action);
    }

    /**
     * @template T
     *
     * @param callable(): T $action
     *
     * @return T
     *
     * @throws StorageIoFailure
     */
    private function shared(callable $action): mixed
    {
        $this->prepareDirectories();

        return $this->lock->shared($action);
    }

    /**
     * @throws StorageIoFailure
     */
    private function prepareDirectories(): void
    {
        $this->files->ensureDirectory($this->layout->pagesDirectory);
        $this->files->ensureDirectory($this->layout->stateDirectory);
    }

    private function uniqueSuffix(): string
    {
        return bin2hex(random_bytes(6));
    }

    private function removeQuietly(?string $path): void
    {
        if ($path === null) {
            return;
        }

        try {
            $this->files->remove($path);
        } catch (StorageIoFailure) {
            // Leftovers are harmless: readers ignore them and a storage check removes them.
        }
    }

    private function removeIndexQuietly(): void
    {
        try {
            $this->index->discard();
        } catch (StorageIoFailure) {
            // The index is unreadable or missing anyway; the next reader rebuilds it.
        }
    }
}
