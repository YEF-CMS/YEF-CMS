<?php

declare(strict_types=1);

namespace Yef\Storage;

use InvalidArgumentException;
use JsonException;
use Override;
use Yef\Domain\Module\EnabledModules;
use Yef\Domain\Module\Exception\ModuleSettingsFailed;
use Yef\Domain\Module\ModuleName;

use function array_filter;
use function array_map;
use function array_values;
use function dirname;
use function in_array;
use function is_array;
use function is_file;
use function is_string;
use function json_decode;
use function json_encode;

/**
 * Keeps the names of the enabled modules in one JSON file. A missing or damaged file reads as "none enabled".
 *
 * @psalm-api Wired in through the DI configuration.
 */
final readonly class FileEnabledModules implements EnabledModules
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
        return $this->load();
    }

    #[Override]
    public function enable(ModuleName $name): void
    {
        $this->change(static fn(array $names): array => in_array($name->value, self::values($names), true) ? $names : [...$names, $name]);
    }

    #[Override]
    public function disable(ModuleName $name): void
    {
        $this->change(static fn(array $names): array => array_values(array_filter(
            $names,
            static fn(ModuleName $enabled): bool => !$enabled->equals($name),
        )));
    }

    /**
     * @param list<ModuleName> $names
     *
     * @return list<string>
     */
    private static function values(array $names): array
    {
        return array_map(static fn(ModuleName $name): string => $name->value, $names);
    }

    /**
     * @param callable(list<ModuleName>): list<ModuleName> $change
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
     * @return list<ModuleName>
     */
    private function load(): array
    {
        if (!is_file($this->file)) {
            return [];
        }

        try {
            /** @psalm-suppress MixedAssignment The shape is checked right below. */
            $decoded = json_decode($this->files->read($this->file), true, flags: \JSON_THROW_ON_ERROR);
            $listed = is_array($decoded) ? ($decoded['enabled'] ?? null) : null;
            if (!is_array($listed)) {
                return [];
            }

            $names = [];
            /** @psalm-suppress MixedAssignment Entries are validated one by one. */
            foreach ($listed as $entry) {
                if (is_string($entry)) {
                    $names[] = new ModuleName($entry);
                }
            }

            return $names;
        } catch (JsonException|InvalidArgumentException|StorageIoFailure) {
            return [];
        }
    }

    /**
     * @param list<ModuleName> $names
     *
     * @throws StorageIoFailure
     * @throws JsonException
     */
    private function store(array $names): void
    {
        $this->files->writeAtomically(
            $this->file,
            json_encode(
                ['enabled' => self::values($names)],
                \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES,
            ) . "\n",
        );
    }
}
