<?php

declare(strict_types=1);

namespace Yef\Domain\Module\Install;

use DateTimeImmutable;
use Yef\Domain\Module\ModuleName;

/**
 * A module that the installer brought, and where it came from.
 */
final readonly class InstalledModule
{
    /**
     * @param ReleaseVersion $version The version of the release it was installed or last updated from.
     * @param string $archiveHash SHA-256 of that release's archive.
     */
    public function __construct(
        public ModuleName $name,
        public GitHubRepository $repository,
        public ReleaseVersion $version,
        public string $archiveHash,
        public DateTimeImmutable $installedAt,
    ) {}
}
