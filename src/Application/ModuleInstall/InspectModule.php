<?php

declare(strict_types=1);

namespace Yef\Application\ModuleInstall;

use Throwable;
use Yef\Domain\Module\Install\Exception\InvalidModule;
use Yef\Domain\Module\Install\Exception\InvalidReleaseVersion;
use Yef\Domain\Module\Install\Exception\InvalidRepositoryAddress;
use Yef\Domain\Module\Install\Exception\ModuleFilesFailed;
use Yef\Domain\Module\Install\Exception\NoRelease;
use Yef\Domain\Module\Install\Exception\RepositoryNotFound;
use Yef\Domain\Module\Install\Exception\SourceUnavailable;
use Yef\Domain\Module\Install\Exception\UnsafeArchive;
use Yef\Domain\Module\Install\GitHubRepository;
use Yef\Domain\Module\Install\ModuleArchive;
use Yef\Domain\Module\Install\ModuleFiles;
use Yef\Domain\Module\Install\ModuleInspector;
use Yef\Domain\Module\Install\ModuleSource;
use Yef\Domain\Module\Install\StagedOffer;

/**
 * Takes the latest release of a repository to a waiting place and shows what it is. Nothing is installed yet:
 * the admin sees the module and confirms it with `InstallModule`.
 *
 * @psalm-api Used by the admin and console adapters.
 */
final readonly class InspectModule
{
    public function __construct(
        private ModuleSource $source,
        private ModuleArchive $archive,
        private ModuleInspector $inspector,
        private ModuleFiles $files,
        private ModuleOffers $offers,
    ) {}

    /**
     * @throws InvalidRepositoryAddress
     * @throws ModuleFolderNotWritable
     * @throws RepositoryNotFound
     * @throws NoRelease
     * @throws InvalidReleaseVersion
     * @throws SourceUnavailable
     * @throws UnsafeArchive
     * @throws InvalidModule
     * @throws ModuleFilesFailed
     */
    public function handle(string $address): ModuleOffer
    {
        $repository = GitHubRepository::fromAddress($address);
        if (!$this->files->canChange()) {
            throw ModuleFolderNotWritable::create();
        }

        $release = $this->source->latestRelease($repository);
        $area = $this->files->newStaging();

        try {
            $hash = $this->source->download($release, $area->archiveFile);
            $this->archive->unpack($area->archiveFile, $area->directory);
            $info = $this->inspector->inspect($area->directory);
            $offer = new StagedOffer($release, $hash, null);
            $this->files->remember($area, $offer);
        } catch (Throwable $throwable) {
            $this->files->discard($area);

            throw $throwable;
        }

        return $this->offers->build($area, $offer, $info);
    }
}
