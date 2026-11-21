<?php

declare(strict_types=1);

namespace Yef\Domain\Module\Install;

use Yef\Domain\Module\Install\Exception\UnsafeArchive;

/**
 * Opens the archive of a module. Nothing in an archive is trusted: where it would write, how much, and what.
 */
interface ModuleArchive
{
    /**
     * Unpacks the archive into an empty directory. When all its files lie in one top folder (as in the archives
     * GitHub makes), that folder is left out, so the directory holds the module itself.
     *
     * @throws UnsafeArchive When the archive cannot be unpacked safely; nothing is left in the directory then.
     */
    public function unpack(string $archiveFile, string $directory): void;
}
