<?php

declare(strict_types=1);

namespace Yef\Storage;

use Override;
use Yef\Domain\User\Exception\UserStorageFailed;
use Yef\Domain\User\SetupToken;

use function bin2hex;
use function dirname;
use function is_file;
use function random_bytes;
use function trim;

/**
 * Keeps the setup code in a file the site owner can read over FTP or in a file manager.
 *
 * @psalm-api Wired in through the DI configuration.
 */
final readonly class FileSetupToken implements SetupToken
{
    private Files $files;

    private FileLock $lock;

    public function __construct(
        private string $file,
        private string $lockFile,
    ) {
        $this->files = new Files();
        $this->lock = new FileLock($lockFile);
    }

    #[Override]
    public function current(): string
    {
        try {
            $this->files->ensureDirectory(dirname($this->lockFile));

            return $this->lock->exclusive(fn(): string => $this->readOrCreate());
        } catch (StorageIoFailure $storageIoFailure) {
            throw UserStorageFailed::whileSaving($storageIoFailure);
        }
    }

    #[Override]
    public function consume(): void
    {
        try {
            $this->files->ensureDirectory(dirname($this->lockFile));
            $this->lock->exclusive(fn() => $this->files->remove($this->file));
        } catch (StorageIoFailure $storageIoFailure) {
            throw UserStorageFailed::whileSaving($storageIoFailure);
        }
    }

    /**
     * @throws StorageIoFailure
     */
    private function readOrCreate(): string
    {
        if (is_file($this->file)) {
            $existing = trim($this->files->read($this->file));
            if ($existing !== '') {
                return $existing;
            }
        }

        $token = bin2hex(random_bytes(16));
        $this->files->writePrivately($this->file, $token . "\n");

        return $token;
    }
}
