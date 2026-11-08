<?php

declare(strict_types=1);

namespace Yef\Storage;

use function fclose;
use function flock;
use function fopen;

/**
 * Serializes access to the storage between concurrent requests.
 *
 * Not re-entrant: code running under the lock must not ask for it again.
 */
final readonly class FileLock
{
    public function __construct(private string $path) {}

    /**
     * @template T
     *
     * @param callable(): T $action
     *
     * @return T
     *
     * @throws StorageIoFailure
     */
    public function exclusive(callable $action): mixed
    {
        return $this->run(\LOCK_EX, $action);
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
    public function shared(callable $action): mixed
    {
        return $this->run(\LOCK_SH, $action);
    }

    /**
     * @template T
     *
     * @param callable(): T $action
     *
     * @return T
     */
    private function run(int $mode, callable $action): mixed
    {
        $handle = fopen($this->path, 'c');
        if ($handle === false) {
            throw StorageIoFailure::during('open lock', $this->path);
        }

        try {
            if (!flock($handle, $mode)) {
                throw StorageIoFailure::during('acquire lock', $this->path);
            }

            return $action();
        } finally {
            flock($handle, \LOCK_UN);
            fclose($handle);
        }
    }
}
