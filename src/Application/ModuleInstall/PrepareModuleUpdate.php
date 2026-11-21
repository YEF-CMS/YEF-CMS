<?php

declare(strict_types=1);

namespace Yef\Application\ModuleInstall;

use InvalidArgumentException;
use Throwable;
use Yef\Domain\Module\Install\Exception\InvalidModule;
use Yef\Domain\Module\Install\Exception\InvalidReleaseVersion;
use Yef\Domain\Module\Install\Exception\ModuleFilesFailed;
use Yef\Domain\Module\Install\Exception\NoRelease;
use Yef\Domain\Module\Install\Exception\RepositoryNotFound;
use Yef\Domain\Module\Install\Exception\SourceUnavailable;
use Yef\Domain\Module\Install\Exception\UnsafeArchive;
use Yef\Domain\Module\Install\InstalledModule;
use Yef\Domain\Module\Install\InstalledModules;
use Yef\Domain\Module\Install\ModuleArchive;
use Yef\Domain\Module\Install\ModuleFiles;
use Yef\Domain\Module\Install\ModuleInspector;
use Yef\Domain\Module\Install\ModuleSource;
use Yef\Domain\Module\Install\StagedOffer;
use Yef\Domain\Module\ModuleName;

/**
 * Takes the latest release of an installed module to a waiting place and shows what it is, when it is newer
 * than what is installed. Nothing is replaced yet: the admin confirms it with `UpdateModule`.
 *
 * @psalm-api Used by the admin and console adapters.
 */
final readonly class PrepareModuleUpdate
{
    public function __construct(
        private InstalledModules $installed,
        private ModuleSource $source,
        private ModuleArchive $archive,
        private ModuleInspector $inspector,
        private ModuleFiles $files,
        private ModuleOffers $offers,
    ) {}

    /**
     * @throws InvalidArgumentException When the name is not "vendor/name".
     * @throws NotInstalledByInstaller
     * @throws ModuleFolderNotWritable
     * @throws ModuleIsUpToDate
     * @throws RepositoryNotFound
     * @throws NoRelease
     * @throws InvalidReleaseVersion
     * @throws SourceUnavailable
     * @throws UnsafeArchive
     * @throws InvalidModule
     * @throws ModuleFilesFailed
     */
    public function handle(string $name): ModuleOffer
    {
        $module = new ModuleName($name);
        $current = $this->installed->find($module);
        if (!$current instanceof InstalledModule) {
            throw NotInstalledByInstaller::named($module);
        }

        if (!$this->files->canChange()) {
            throw ModuleFolderNotWritable::create();
        }

        $release = $this->source->latestRelease($current->repository);
        if (!$release->version->isNewerThan($current->version)) {
            throw ModuleIsUpToDate::named($module, $current->version);
        }

        $area = $this->files->newStaging();

        try {
            $hash = $this->source->download($release, $area->archiveFile);
            $this->archive->unpack($area->archiveFile, $area->directory);
            $info = $this->inspector->inspect($area->directory);
            $offer = new StagedOffer($release, $hash, $module);
            $this->files->remember($area, $offer);
        } catch (Throwable $throwable) {
            $this->files->discard($area);

            throw $throwable;
        }

        return $this->offers->build($area, $offer, $info);
    }
}
