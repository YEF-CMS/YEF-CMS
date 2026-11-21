<?php

declare(strict_types=1);

namespace Yef\Storage;

use InvalidArgumentException;
use JsonException;
use Override;
use Yef\Domain\Settings\AdminLanguageSetting;
use Yef\Domain\Settings\Exception\SettingsFailed;
use Yef\Domain\Settings\Language;

use function dirname;
use function is_array;
use function is_file;
use function is_string;
use function json_decode;
use function json_encode;

/**
 * Keeps the site settings in one JSON file; the admin language is the "language" entry.
 * A missing or damaged file reads as "nothing chosen". Other entries of the file are kept when the language changes.
 *
 * @psalm-api Wired in through the DI configuration.
 */
final readonly class FileAdminLanguageSetting implements AdminLanguageSetting
{
    private const string KEY = 'language';

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
    public function chosen(): ?Language
    {
        $code = $this->load()[self::KEY] ?? null;
        if (!is_string($code)) {
            return null;
        }

        try {
            return new Language($code);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    #[Override]
    public function choose(Language $language): void
    {
        try {
            $this->files->ensureDirectory(dirname($this->lockFile));
            $this->files->ensureDirectory(dirname($this->file));
            $this->lock->exclusive(function () use ($language): void {
                $this->files->writeAtomically(
                    $this->file,
                    json_encode(
                        [...$this->load(), self::KEY => $language->code],
                        \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES,
                    ) . "\n",
                );
            });
        } catch (StorageIoFailure|JsonException $failure) {
            throw SettingsFailed::whileSaving($failure);
        }
    }

    /**
     * @return array<array-key, mixed>
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

        return is_array($decoded) ? $decoded : [];
    }
}
