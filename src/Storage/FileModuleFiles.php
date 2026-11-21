<?php

declare(strict_types=1);

namespace Yef\Storage;

use DirectoryIterator;
use InvalidArgumentException;
use JsonException;
use Override;
use Yef\Domain\Module\Install\Exception\InvalidReleaseVersion;
use Yef\Domain\Module\Install\Exception\InvalidRepositoryAddress;
use Yef\Domain\Module\Install\Exception\ModuleFilesFailed;
use Yef\Domain\Module\Install\Exception\StagingNotFound;
use Yef\Domain\Module\Install\GitHubRepository;
use Yef\Domain\Module\Install\ModuleFiles;
use Yef\Domain\Module\Install\ModuleRelease;
use Yef\Domain\Module\Install\ReleaseVersion;
use Yef\Domain\Module\Install\StagedOffer;
use Yef\Domain\Module\Install\StagingArea;
use Yef\Domain\Module\ModuleName;

use function bin2hex;
use function dirname;
use function filemtime;
use function is_array;
use function is_dir;
use function is_file;
use function is_string;
use function is_writable;
use function json_decode;
use function json_encode;
use function preg_match;
use function random_bytes;
use function time;

/**
 * The folder of the modules and the places where downloaded modules wait. A module is put in place, replaced and
 * removed with renames, so a module is never seen half there.
 *
 * @psalm-api Wired in through the DI configuration.
 */
final readonly class FileModuleFiles implements ModuleFiles
{
    private const string OFFER_FILE = 'offer.json';

    private const string UNPACKED = 'module';

    private const string ARCHIVE = 'archive.zip';

    private Files $files;

    /**
     * @param int $stagingLifetime Seconds a downloaded module may wait before it is thrown away.
     */
    public function __construct(
        private string $modulesDirectory,
        private string $stagingDirectory,
        private int $stagingLifetime,
    ) {
        $this->files = new Files();
    }

    #[Override]
    public function canChange(): bool
    {
        return is_dir($this->modulesDirectory) && is_writable($this->modulesDirectory);
    }

    #[Override]
    public function isInstalled(ModuleName $module): bool
    {
        return is_dir($this->pathOf($module));
    }

    #[Override]
    public function newStaging(): StagingArea
    {
        try {
            $this->files->ensureDirectory($this->stagingDirectory);
            $this->discardExpired();
            $token = bin2hex(random_bytes(16));
            $directory = $this->stagingDirectory . '/' . $token;
            $this->files->ensureDirectory($directory . '/' . self::UNPACKED);
        } catch (StorageIoFailure $storageIoFailure) {
            throw ModuleFilesFailed::because('the place for downloaded modules cannot be made', $storageIoFailure);
        }

        return $this->areaOf($token);
    }

    #[Override]
    public function staging(string $token): StagingArea
    {
        if (preg_match('/^[a-f0-9]{32}$/', $token) !== 1) {
            throw StagingNotFound::create();
        }

        $area = $this->areaOf($token);
        $directory = dirname($area->archiveFile);
        if (!is_dir($directory) || $this->isExpired($directory)) {
            $this->discard($area);

            throw StagingNotFound::create();
        }

        return $area;
    }

    #[Override]
    public function remember(StagingArea $area, StagedOffer $offer): void
    {
        try {
            $this->files->writeAtomically(
                dirname($area->archiveFile) . '/' . self::OFFER_FILE,
                json_encode([
                    'repository' => $offer->release->repository->__toString(),
                    'version' => $offer->release->version->value,
                    'archiveAddress' => $offer->release->archiveAddress,
                    'archiveHash' => $offer->archiveHash,
                    'replaces' => $offer->replaces?->value,
                ], \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES) . "\n",
            );
        } catch (StorageIoFailure|JsonException $failure) {
            throw ModuleFilesFailed::because('the downloaded module cannot be remembered', $failure);
        }
    }

    #[Override]
    public function offerOf(StagingArea $area): StagedOffer
    {
        $file = dirname($area->archiveFile) . '/' . self::OFFER_FILE;
        if (!is_file($file)) {
            throw StagingNotFound::create();
        }

        try {
            /** @psalm-suppress MixedAssignment The shape is checked right below. */
            $data = json_decode($this->files->read($file), true, flags: \JSON_THROW_ON_ERROR);
            $repository = is_array($data) ? ($data['repository'] ?? null) : null;
            $version = is_array($data) ? ($data['version'] ?? null) : null;
            $address = is_array($data) ? ($data['archiveAddress'] ?? null) : null;
            $hash = is_array($data) ? ($data['archiveHash'] ?? null) : null;
            $replaces = is_array($data) ? ($data['replaces'] ?? null) : null;
            if (!is_string($repository) || !is_string($version) || !is_string($address) || !is_string($hash)
                || ($replaces !== null && !is_string($replaces))
            ) {
                throw StagingNotFound::create();
            }

            return new StagedOffer(
                new ModuleRelease(
                    GitHubRepository::fromAddress('https://github.com/' . $repository),
                    new ReleaseVersion($version),
                    $address,
                ),
                $hash,
                $replaces === null ? null : new ModuleName($replaces),
            );
        } catch (JsonException|StorageIoFailure|InvalidRepositoryAddress|InvalidReleaseVersion|InvalidArgumentException) {
            throw StagingNotFound::create();
        }
    }

    #[Override]
    public function discard(StagingArea $area): void
    {
        try {
            $this->files->remove(dirname($area->archiveFile));
        } catch (StorageIoFailure) {
            // What stays is thrown away with the next area that is made, once it has waited long enough.
        }
    }

    #[Override]
    public function install(StagingArea $area, ModuleName $module): void
    {
        $target = $this->pathOf($module);
        if (is_dir($target)) {
            throw ModuleFilesFailed::because('a module with this name is already there');
        }

        try {
            $this->files->move($area->directory, $target);
        } catch (StorageIoFailure $storageIoFailure) {
            throw ModuleFilesFailed::because('the module cannot be moved into its place', $storageIoFailure);
        }

        $this->discard($area);
    }

    #[Override]
    public function replace(StagingArea $area, ModuleName $module): void
    {
        $target = $this->pathOf($module);
        if (!is_dir($target)) {
            throw ModuleFilesFailed::because('the module to replace is not there');
        }

        $old = $target . '.old-' . bin2hex(random_bytes(4));

        try {
            $this->files->move($target, $old);
        } catch (StorageIoFailure $storageIoFailure) {
            throw ModuleFilesFailed::because('the installed module cannot be set aside', $storageIoFailure);
        }

        try {
            $this->files->move($area->directory, $target);
        } catch (StorageIoFailure $storageIoFailure) {
            // The module that was there comes back, as if nothing had been tried.
            $this->files->move($old, $target);

            throw ModuleFilesFailed::because('the new module cannot be moved into its place', $storageIoFailure);
        }

        try {
            $this->files->remove($old);
        } catch (StorageIoFailure) {
            // The new module is in place; what is left of the old one is only clutter.
        }

        $this->discard($area);
    }

    #[Override]
    public function remove(ModuleName $module): void
    {
        try {
            $this->files->remove($this->pathOf($module));
        } catch (StorageIoFailure $storageIoFailure) {
            throw ModuleFilesFailed::because('the module cannot be removed', $storageIoFailure);
        }
    }

    private function pathOf(ModuleName $module): string
    {
        return $this->modulesDirectory . '/' . $module->directoryName();
    }

    private function areaOf(string $token): StagingArea
    {
        $directory = $this->stagingDirectory . '/' . $token;

        return new StagingArea($token, $directory . '/' . self::ARCHIVE, $directory . '/' . self::UNPACKED);
    }

    private function isExpired(string $directory): bool
    {
        $changed = filemtime($directory);

        return $changed === false || time() - $changed > $this->stagingLifetime;
    }

    private function discardExpired(): void
    {
        if (!is_dir($this->stagingDirectory)) {
            return;
        }

        foreach (new DirectoryIterator($this->stagingDirectory) as $item) {
            if (!$item->isDot() && $item->isDir() && $this->isExpired($item->getPathname())) {
                try {
                    $this->files->remove($item->getPathname());
                } catch (StorageIoFailure) {
                    // Tried again next time.
                }
            }
        }
    }
}
