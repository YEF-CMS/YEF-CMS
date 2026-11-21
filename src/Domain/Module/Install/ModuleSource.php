<?php

declare(strict_types=1);

namespace Yef\Domain\Module\Install;

use Yef\Domain\Module\Install\Exception\InvalidReleaseVersion;
use Yef\Domain\Module\Install\Exception\NoRelease;
use Yef\Domain\Module\Install\Exception\RepositoryNotFound;
use Yef\Domain\Module\Install\Exception\SourceUnavailable;

/**
 * Where modules come from: the releases of repositories. The only port to the network.
 */
interface ModuleSource
{
    /**
     * The latest published release (a draft or a pre-release is not one).
     *
     * @throws RepositoryNotFound
     * @throws NoRelease When the repository has no release at all.
     * @throws InvalidReleaseVersion When the release is not named after a semantic version.
     * @throws SourceUnavailable
     */
    public function latestRelease(GitHubRepository $repository): ModuleRelease;

    /**
     * Downloads the archive of a release to a file.
     *
     * @return string The SHA-256 of the archive.
     *
     * @throws SourceUnavailable
     */
    public function download(ModuleRelease $release, string $file): string;
}
