<?php

declare(strict_types=1);

namespace Yef\Storage;

use DirectoryIterator;
use JsonException;
use Override;
use Yef\Domain\Page\Exception\PageStorageFailed;
use Yef\Domain\Page\IntegrityReport;
use Yef\Domain\Page\PageId;
use Yef\Domain\Page\PageStorageIntegrity;

use function basename;
use function count;
use function is_dir;
use function is_file;
use function preg_match;

/**
 * Cleans up after interrupted writes of the file storage.
 *
 * A write that died between replacing a page directory and finishing leaves the previous version under a
 * ".old-" name. If the page itself is missing, that version is put back: the operation never reported success.
 *
 * @psalm-api Wired in through the DI configuration.
 */
final readonly class FilePageIntegrity implements PageStorageIntegrity
{
    /** Names that a write in progress gives to the directories it is working with. */
    private const string PREVIOUS_VERSION = '/^([^.]+)\.old-[0-9a-f]{12}$/';

    private const string UNFINISHED_VERSION = '/^([^.]+)\.new-[0-9a-f]{12}$/';

    private const string UNFINISHED_FILE = '/\.tmp-[0-9a-f]{12}$/';

    private Files $files;

    private FileLock $lock;

    private PageIndex $index;

    private PageScanner $scanner;

    public function __construct(private StorageLayout $layout)
    {
        $this->files = new Files();
        $records = new PageMetadataRecord();
        $this->lock = new FileLock($layout->lockFile());
        $this->index = new PageIndex($layout->indexFile(), $this->files, $records);
        $this->scanner = new PageScanner($layout, $this->files, $records);
    }

    #[Override]
    public function check(): IntegrityReport
    {
        try {
            $this->prepareDirectories();

            return $this->lock->shared(fn(): IntegrityReport => $this->report());
        } catch (StorageIoFailure $storageIoFailure) {
            throw PageStorageFailed::whileInspecting($storageIoFailure);
        }
    }

    #[Override]
    public function repair(): IntegrityReport
    {
        try {
            $this->prepareDirectories();

            return $this->lock->exclusive(function (): IntegrityReport {
                foreach ($this->leftovers() as $leftover) {
                    $this->resolve($leftover);
                }

                $this->index->store($this->scanner->scan()->pages);

                return $this->report();
            });
        } catch (StorageIoFailure|JsonException $failure) {
            throw PageStorageFailed::whileInspecting($failure);
        }
    }

    /**
     * @throws StorageIoFailure
     */
    private function report(): IntegrityReport
    {
        return new IntegrityReport(count($this->leftovers()), $this->scanner->scan()->damagedPages);
    }

    /**
     * @throws StorageIoFailure
     */
    private function resolve(string $leftover): void
    {
        $page = $this->pageNamedBy(self::PREVIOUS_VERSION, basename($leftover));
        $current = $page instanceof PageId ? $this->layout->pageDirectory($page) : null;

        if ($current !== null && !is_dir($current) && is_file($this->layout->metadataFile($leftover))) {
            $this->files->move($leftover, $current);

            return;
        }

        $this->files->remove($leftover);
    }

    /**
     * @return list<string>
     */
    private function leftovers(): array
    {
        $leftovers = [];
        foreach ($this->entries($this->layout->pagesDirectory) as $name => $path) {
            if ($this->isUnfinishedDirectory($name)) {
                $leftovers[] = $path;
            } elseif ($this->layout->pageIdOfDirectoryName($name) instanceof PageId) {
                $leftovers = [...$leftovers, ...$this->unfinishedFilesIn($path)];
            }
        }

        return [...$leftovers, ...$this->unfinishedFilesIn($this->layout->stateDirectory)];
    }

    private function isUnfinishedDirectory(string $name): bool
    {
        return array_any(
            [self::PREVIOUS_VERSION, self::UNFINISHED_VERSION],
            fn(string $pattern): bool => $this->pageNamedBy($pattern, $name) instanceof PageId,
        );
    }

    /**
     * @param non-empty-string $pattern
     *
     * @return PageId|null The page a leftover belongs to, when the name matches the pattern.
     */
    private function pageNamedBy(string $pattern, string $name): ?PageId
    {
        if (preg_match($pattern, $name, $match) !== 1 || !isset($match[1])) {
            return null;
        }

        return $this->layout->pageIdOfDirectoryName($match[1]);
    }

    /**
     * @return list<string> Unfinished files of a page directory, its payload directory or the state directory.
     */
    private function unfinishedFilesIn(string $directory): array
    {
        $files = [];
        foreach ([$directory, $this->layout->payloadDirectory($directory)] as $candidate) {
            foreach ($this->entries($candidate) as $name => $path) {
                if (is_file($path) && preg_match(self::UNFINISHED_FILE, $name) === 1) {
                    $files[] = $path;
                }
            }
        }

        return $files;
    }

    /**
     * @return array<string, string> Entry name => its path.
     */
    private function entries(string $directory): array
    {
        $entries = [];
        if (!is_dir($directory)) {
            return $entries;
        }

        foreach (new DirectoryIterator($directory) as $entry) {
            if (!$entry->isDot()) {
                $entries[$entry->getFilename()] = $entry->getPathname();
            }
        }

        return $entries;
    }

    /**
     * @throws StorageIoFailure
     */
    private function prepareDirectories(): void
    {
        $this->files->ensureDirectory($this->layout->pagesDirectory);
        $this->files->ensureDirectory($this->layout->stateDirectory);
    }
}
