<?php

declare(strict_types=1);

namespace Yef\Storage;

use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use JsonException;
use Override;
use Yef\Domain\Module\Exception\ModuleSettingsFailed;
use Yef\Domain\Module\Install\Exception\InvalidReleaseVersion;
use Yef\Domain\Module\Install\Exception\InvalidRepositoryAddress;
use Yef\Domain\Module\Install\GitHubRepository;
use Yef\Domain\Module\Install\InstalledModule;
use Yef\Domain\Module\Install\InstalledModules;
use Yef\Domain\Module\Install\ReleaseVersion;
use Yef\Domain\Module\ModuleName;

use function array_values;
use function dirname;
use function is_array;
use function is_file;
use function is_string;
use function json_decode;
use function json_encode;
use function ksort;
use function usort;

/**
 * Keeps where the installed modules came from in one JSON file (`data/modules.lock.json`). A missing or damaged
 * file reads as "nothing installed by the installer"; such modules then stay where they are, only without a source.
 *
 * @psalm-api Wired in through the DI configuration.
 */
final readonly class FileInstalledModules implements InstalledModules
{
    private Files $files;

    private FileLock $lock;

    public function __construct(
        private string $file,
        private string $lockFile,
    ) {
        $this->files = new Files();
        $this->lock = new FileLock($lockFile);
    }

    #[Override]
    public function all(): array
    {
        $modules = array_values($this->load());
        usort($modules, static fn(InstalledModule $a, InstalledModule $b): int => $a->name->value <=> $b->name->value);

        return $modules;
    }

    #[Override]
    public function find(ModuleName $module): ?InstalledModule
    {
        return $this->load()[$module->value] ?? null;
    }

    #[Override]
    public function record(InstalledModule $module): void
    {
        $this->change(static function (array $modules) use ($module): array {
            $modules[$module->name->value] = $module;

            return $modules;
        });
    }

    #[Override]
    public function forget(ModuleName $module): void
    {
        $this->change(static function (array $modules) use ($module): array {
            unset($modules[$module->value]);

            return $modules;
        });
    }

    /**
     * @param callable(array<string, InstalledModule>): array<string, InstalledModule> $change
     *
     * @throws ModuleSettingsFailed
     */
    private function change(callable $change): void
    {
        try {
            $this->files->ensureDirectory(dirname($this->lockFile));
            $this->files->ensureDirectory(dirname($this->file));
            $this->lock->exclusive(fn() => $this->store($change($this->load())));
        } catch (StorageIoFailure|JsonException $failure) {
            throw ModuleSettingsFailed::whileSaving($failure);
        }
    }

    /**
     * @return array<string, InstalledModule>
     */
    private function load(): array
    {
        if (!is_file($this->file)) {
            return [];
        }

        try {
            /** @psalm-suppress MixedAssignment The shape is checked right below. */
            $decoded = json_decode($this->files->read($this->file), true, flags: \JSON_THROW_ON_ERROR);
        } catch (JsonException|StorageIoFailure) {
            return [];
        }

        $listed = is_array($decoded) ? ($decoded['modules'] ?? null) : null;
        if (!is_array($listed)) {
            return [];
        }

        $modules = [];
        /** @psalm-suppress MixedAssignment Entries are validated one by one. */
        foreach ($listed as $name => $entry) {
            $module = is_array($entry) ? $this->fromEntry((string) $name, $entry) : null;
            if ($module instanceof InstalledModule) {
                $modules[$module->name->value] = $module;
            }
        }

        return $modules;
    }

    /**
     * @param array<array-key, mixed> $entry
     */
    private function fromEntry(string $name, array $entry): ?InstalledModule
    {
        $repository = $entry['repository'] ?? null;
        $version = $entry['version'] ?? null;
        $archive = $entry['archive'] ?? null;
        $installedAt = $entry['installedAt'] ?? null;
        if (!is_string($repository) || !is_string($version) || !is_string($archive) || !is_string($installedAt)) {
            return null;
        }

        try {
            return new InstalledModule(
                new ModuleName($name),
                GitHubRepository::fromAddress('https://github.com/' . $repository),
                new ReleaseVersion($version),
                $archive,
                new DateTimeImmutable($installedAt),
            );
        } catch (InvalidArgumentException|InvalidRepositoryAddress|InvalidReleaseVersion|Exception) {
            return null;
        }
    }

    /**
     * @param array<string, InstalledModule> $modules
     *
     * @throws StorageIoFailure
     * @throws JsonException
     */
    private function store(array $modules): void
    {
        ksort($modules);
        $entries = [];
        foreach ($modules as $name => $module) {
            $entries[$name] = [
                'repository' => $module->repository->__toString(),
                'version' => $module->version->value,
                'archive' => $module->archiveHash,
                'installedAt' => $module->installedAt->format(\DATE_ATOM),
            ];
        }

        $this->files->writeAtomically(
            $this->file,
            json_encode(['modules' => $entries], \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES) . "\n",
        );
    }
}
