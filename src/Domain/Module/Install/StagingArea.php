<?php

declare(strict_types=1);

namespace Yef\Domain\Module\Install;

/**
 * A place where a downloaded module waits until the admin confirms it: the archive and the unpacked module.
 */
final readonly class StagingArea
{
    /**
     * @param string $token Names the area; given back by the admin when the module is confirmed.
     * @param string $archiveFile Where the archive is saved.
     * @param string $directory Where the module is unpacked, empty at first.
     */
    public function __construct(
        public string $token,
        public string $archiveFile,
        public string $directory,
    ) {}
}
