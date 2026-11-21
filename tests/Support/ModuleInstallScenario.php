<?php

declare(strict_types=1);

namespace Yef\Tests\Support;

use Yef\Application\ModuleInstall\CheckModuleUpdates;
use Yef\Application\ModuleInstall\GetModuleOffer;
use Yef\Application\ModuleInstall\InspectModule;
use Yef\Application\ModuleInstall\InstallModule;
use Yef\Application\ModuleInstall\ListInstalledModules;
use Yef\Application\ModuleInstall\ModuleOffers;
use Yef\Application\ModuleInstall\PrepareModuleUpdate;
use Yef\Application\ModuleInstall\RemoveModule;
use Yef\Application\ModuleInstall\UpdateModule;
use Yef\Domain\Module\Install\InstalledModules;
use Yef\Module\ManifestModuleInspector;
use Yef\Module\ManifestReader;
use Yef\Platform\ZipModuleArchive;
use Yef\Storage\FileModuleFiles;

/**
 * The module installer wired to a temporary folder of modules, real zip archives and a fake GitHub.
 */
final readonly class ModuleInstallScenario
{
    public const string CORE_VERSION = '0.1.0';

    public TemporaryDirectory $directory;

    public FakeModuleSource $source;

    public InstalledModules $installed;

    public InMemoryEnabledModules $enabled;

    public FileModuleFiles $files;

    public InspectModule $inspect;

    public GetModuleOffer $getOffer;

    public InstallModule $install;

    public PrepareModuleUpdate $prepareUpdate;

    public UpdateModule $update;

    public RemoveModule $remove;

    public CheckModuleUpdates $checkUpdates;

    public ListInstalledModules $list;

    public function __construct(?InstalledModules $installed = null)
    {
        $this->directory = new TemporaryDirectory();
        mkdir($this->modules(), 0o777, true);
        $this->source = new FakeModuleSource();
        $this->installed = $installed ?? new InMemoryInstalledModules();
        $this->enabled = new InMemoryEnabledModules();
        $this->files = new FileModuleFiles($this->modules(), $this->directory->path . '/var/incoming', 3600);
        $inspector = new ManifestModuleInspector(new ManifestReader(), $this->modules(), self::CORE_VERSION);
        $offers = new ModuleOffers($this->files, $inspector, $this->installed);
        $this->getOffer = new GetModuleOffer($this->files, $inspector, $offers);
        $this->inspect = new InspectModule($this->source, new ZipModuleArchive(100, 1_000_000), $inspector, $this->files, $offers);
        $this->install = new InstallModule($this->getOffer, $this->files, $this->installed, new FrozenClock());
        $this->prepareUpdate = new PrepareModuleUpdate($this->installed, $this->source, new ZipModuleArchive(100, 1_000_000), $inspector, $this->files, $offers);
        $this->update = new UpdateModule($this->getOffer, $this->files, $this->installed, new FrozenClock());
        $this->remove = new RemoveModule($this->installed, $this->enabled, $this->files);
        $this->checkUpdates = new CheckModuleUpdates($this->installed, $this->source);
        $this->list = new ListInstalledModules($this->installed);
    }

    public function modules(): string
    {
        return $this->directory->path . '/modules';
    }

    /**
     * Makes a release of a repository on the fake GitHub: a module named like the repository, unless told otherwise.
     *
     * @param array<string, mixed> $manifest
     * @param array<string, string> $extraFiles
     */
    public function publish(string $repository, string $version, ?string $moduleName = null, array $manifest = [], array $extraFiles = []): void
    {
        $zip = $this->directory->path . '/' . str_replace('/', '_', $repository) . '-' . $version . '.zip';
        ModuleZip::module($zip, $moduleName ?? $repository, [...['version' => $version], ...$manifest], $extraFiles);
        $this->source->release($repository, $version, $zip);
    }

    public function address(string $repository): string
    {
        return 'https://github.com/' . $repository;
    }

    /**
     * @return list<string> What waits in staging areas.
     */
    public function waiting(): array
    {
        $directory = $this->directory->path . '/var/incoming';

        return is_dir($directory) ? DirectoryListing::of($directory) : [];
    }

    public function cleanUp(): void
    {
        $this->directory->delete();
    }
}
