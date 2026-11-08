<?php

declare(strict_types=1);

namespace Yef\Storage;

use DirectoryIterator;

use function bin2hex;
use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function is_file;
use function is_link;
use function mkdir;
use function random_bytes;
use function rename;
use function rmdir;
use function unlink;

/**
 * The few file operations the storage needs, each failing loudly instead of returning false.
 */
final readonly class Files
{
    /**
     * @throws StorageIoFailure
     */
    public function ensureDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0o775, true) && !is_dir($path)) {
            throw StorageIoFailure::during('create directory', $path);
        }
    }

    /**
     * @throws StorageIoFailure
     */
    public function read(string $path): string
    {
        $contents = is_file($path) ? file_get_contents($path) : false;

        return $contents === false ? throw StorageIoFailure::during('read', $path) : $contents;
    }

    /**
     * Readers see either the old contents or the new ones, never a half-written file.
     *
     * @throws StorageIoFailure
     */
    public function writeAtomically(string $path, string $contents): void
    {
        $temporary = $path . '.tmp-' . bin2hex(random_bytes(6));
        if (file_put_contents($temporary, $contents) === false) {
            $this->removeFile($temporary);

            throw StorageIoFailure::during('write', $path);
        }

        $this->move($temporary, $path);
    }

    /**
     * @throws StorageIoFailure
     */
    public function move(string $from, string $to): void
    {
        if (!rename($from, $to)) {
            throw StorageIoFailure::during('move', $from);
        }
    }

    /**
     * Removes a file or a whole directory tree. A missing target is not an error.
     *
     * @throws StorageIoFailure
     */
    public function remove(string $path): void
    {
        if (is_link($path) || !is_dir($path)) {
            $this->removeFile($path);

            return;
        }

        $children = [];
        foreach (new DirectoryIterator($path) as $item) {
            if (!$item->isDot()) {
                $children[] = $item->getPathname();
            }
        }

        foreach ($children as $child) {
            $this->remove($child);
        }

        $this->removeDirectory($path);
    }

    private function removeFile(string $path): void
    {
        if ((is_file($path) || is_link($path)) && !unlink($path)) {
            throw StorageIoFailure::during('remove', $path);
        }
    }

    private function removeDirectory(string $path): void
    {
        if (!rmdir($path)) {
            throw StorageIoFailure::during('remove directory', $path);
        }
    }
}
