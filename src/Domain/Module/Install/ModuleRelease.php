<?php

declare(strict_types=1);

namespace Yef\Domain\Module\Install;

/**
 * The latest release of a module's repository.
 */
final readonly class ModuleRelease
{
    /**
     * @param string $archiveAddress Where the archive of the release is downloaded from.
     */
    public function __construct(
        public GitHubRepository $repository,
        public ReleaseVersion $version,
        public string $archiveAddress,
    ) {}
}
