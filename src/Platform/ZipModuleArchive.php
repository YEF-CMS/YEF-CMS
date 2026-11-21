<?php

declare(strict_types=1);

namespace Yef\Platform;

use FilesystemIterator;
use Override;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Yef\Domain\Module\Install\Exception\UnsafeArchive;
use Yef\Domain\Module\Install\ModuleArchive;
use ZipArchive;

use function count;
use function dirname;
use function explode;
use function fclose;
use function feof;
use function fopen;
use function fread;
use function fwrite;
use function is_dir;
use function is_file;
use function mkdir;
use function preg_match;
use function rmdir;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function substr;
use function unlink;

/**
 * Unpacks the zip archive of a module, trusting nothing in it: no paths that leave the target directory,
 * no links, a limit on the number of files and on how much they add up to (counted while writing, not taken
 * from the archive's own word), and nothing is left behind when something is wrong.
 *
 * @psalm-api Wired in through the DI configuration.
 */
final readonly class ZipModuleArchive implements ModuleArchive
{
    private const int UNIX_LINK = 0o120000;

    private const int UNIX_TYPE_MASK = 0o170000;

    public function __construct(
        private int $maxFiles,
        private int $maxUnpackedBytes,
    ) {}

    #[Override]
    public function unpack(string $archiveFile, string $directory): void
    {
        $zip = new ZipArchive();
        if ($zip->open($archiveFile, ZipArchive::RDONLY) !== true) {
            throw UnsafeArchive::because(UnsafeArchive::NOT_AN_ARCHIVE);
        }

        try {
            $entries = $this->entries($zip);
            $this->extract($zip, $entries, $directory);
        } catch (UnsafeArchive $unsafeArchive) {
            $this->clear($directory);

            throw $unsafeArchive;
        } finally {
            $zip->close();
        }
    }

    /**
     * @return list<array{index: int, name: string}>
     *
     * @throws UnsafeArchive
     */
    private function entries(ZipArchive $zip): array
    {
        $count = $zip->numFiles;
        if ($count === 0) {
            throw UnsafeArchive::because(UnsafeArchive::EMPTY);
        }

        if ($count > $this->maxFiles) {
            throw UnsafeArchive::because(UnsafeArchive::TOO_MANY_FILES, $count . ' files');
        }

        $entries = [];
        $declared = 0;
        for ($index = 0; $index < $count; ++$index) {
            /** @var array{name: string, size: int}|false $stat */
            $stat = $zip->statIndex($index);
            if ($stat === false) {
                throw UnsafeArchive::because(UnsafeArchive::NOT_AN_ARCHIVE);
            }

            $name = $stat['name'];
            $this->assertPathIsSafe($name);
            $this->assertNotALink($zip, $index, $name);
            $declared += $stat['size'];
            if ($declared > $this->maxUnpackedBytes) {
                throw UnsafeArchive::because(UnsafeArchive::TOO_LARGE);
            }

            $entries[] = ['index' => $index, 'name' => $name];
        }

        return $entries;
    }

    /**
     * @throws UnsafeArchive
     */
    private function assertPathIsSafe(string $name): void
    {
        if ($name === '' || strlen($name) > 255 || str_contains($name, "\0") || str_contains($name, '\\')
            || str_starts_with($name, '/') || preg_match('/^[A-Za-z]:/', $name) === 1
        ) {
            throw UnsafeArchive::because(UnsafeArchive::BAD_PATH, $name);
        }

        foreach (explode('/', $name) as $segment) {
            if ($segment === '..') {
                throw UnsafeArchive::because(UnsafeArchive::BAD_PATH, $name);
            }
        }
    }

    /**
     * @throws UnsafeArchive
     */
    private function assertNotALink(ZipArchive $zip, int $index, string $name): void
    {
        $system = 0;
        $attributes = 0;
        if ($zip->getExternalAttributesIndex($index, $system, $attributes) && $system === ZipArchive::OPSYS_UNIX
            && (($attributes >> 16) & self::UNIX_TYPE_MASK) === self::UNIX_LINK
        ) {
            throw UnsafeArchive::because(UnsafeArchive::LINK, $name);
        }
    }

    /**
     * @param list<array{index: int, name: string}> $entries
     *
     * @throws UnsafeArchive
     */
    private function extract(ZipArchive $zip, array $entries, string $directory): void
    {
        $prefix = $this->commonFolder($entries);
        $written = 0;

        foreach ($entries as $entry) {
            $relative = substr($entry['name'], strlen($prefix));
            if ($relative === '' || $relative === '/') {
                continue;
            }

            $target = $directory . '/' . $relative;
            if (str_ends_with($relative, '/')) {
                $this->makeDirectory($target);

                continue;
            }

            $this->makeDirectory(dirname($target));
            $written = $this->write($zip, $entry['name'], $target, $written);
        }
    }

    /**
     * @param list<array{index: int, name: string}> $entries
     *
     * @return string "folder/" when all the files lie in this one folder, else the empty string.
     */
    private function commonFolder(array $entries): string
    {
        $first = null;
        foreach ($entries as $entry) {
            $parts = explode('/', $entry['name']);
            if (count($parts) < 2) {
                return '';
            }

            $first ??= $parts[0];
            if ($parts[0] !== $first) {
                return '';
            }
        }

        return $first === null ? '' : $first . '/';
    }

    /**
     * @throws UnsafeArchive
     */
    private function write(ZipArchive $zip, string $name, string $target, int $written): int
    {
        $source = $zip->getStream($name);
        $out = $source === false ? false : fopen($target, 'wb');
        if ($source === false || $out === false) {
            throw UnsafeArchive::because(UnsafeArchive::WRITE_FAILED, $name);
        }

        while (!feof($source)) {
            $chunk = fread($source, 65536);
            if ($chunk === false) {
                break;
            }

            $written += strlen($chunk);
            if ($written > $this->maxUnpackedBytes) {
                fclose($source);
                fclose($out);

                throw UnsafeArchive::because(UnsafeArchive::TOO_LARGE);
            }

            fwrite($out, $chunk);
        }

        fclose($source);
        fclose($out);

        return $written;
    }

    /**
     * @throws UnsafeArchive
     */
    private function makeDirectory(string $path): void
    {
        if (!is_dir($path) && !@mkdir($path, 0o755, true) && !is_dir($path)) {
            throw UnsafeArchive::because(UnsafeArchive::WRITE_FAILED, $path);
        }
    }

    private function clear(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        /** @var SplFileInfo $item */
        foreach ($items as $item) {
            $path = $item->getPathname();
            if ($item->isDir() && !$item->isLink()) {
                rmdir($path);
            } elseif (is_file($path) || $item->isLink()) {
                unlink($path);
            }
        }
    }
}
