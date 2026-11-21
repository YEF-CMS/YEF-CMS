<?php

declare(strict_types=1);

namespace Yef\Tests\Support;

use Override;
use RuntimeException;
use Throwable;
use Yef\Domain\Module\Install\GitHubRepository;
use Yef\Domain\Module\Install\ModuleRelease;
use Yef\Domain\Module\Install\ModuleSource;
use Yef\Domain\Module\Install\ReleaseVersion;

/**
 * A GitHub that knows the repositories a test gives it. Each has a release (version and archive file) or a failure.
 */
final class FakeModuleSource implements ModuleSource
{
    public int $latestReleaseCalls = 0;

    public int $downloads = 0;

    /** @var array<string, array{version: string, archive: string}|Throwable> */
    private array $repositories = [];

    public function release(string $repository, string $version, string $archiveFile): void
    {
        $this->repositories[$repository] = ['version' => $version, 'archive' => $archiveFile];
    }

    public function fail(string $repository, Throwable $failure): void
    {
        $this->repositories[$repository] = $failure;
    }

    #[Override]
    public function latestRelease(GitHubRepository $repository): ModuleRelease
    {
        ++$this->latestReleaseCalls;
        $known = $this->repositories[$repository->__toString()] ?? throw new RuntimeException('The test did not set up ' . $repository->__toString());
        if ($known instanceof Throwable) {
            throw $known;
        }

        return new ModuleRelease($repository, new ReleaseVersion($known['version']), 'https://api.github.com/archive/' . $repository->name);
    }

    #[Override]
    public function download(ModuleRelease $release, string $file): string
    {
        ++$this->downloads;
        $known = $this->repositories[$release->repository->__toString()] ?? throw new RuntimeException('Not set up.');
        if ($known instanceof Throwable) {
            throw $known;
        }

        copy($known['archive'], $file);

        return (string) hash_file('sha256', $file);
    }
}
