<?php

declare(strict_types=1);

namespace Yef\Storage;

use Override;
use Yef\Domain\Module\DefaultModules;
use Yef\Domain\Module\Exception\ModuleSettingsFailed;

use function dirname;
use function is_file;

/**
 * Starts the list of enabled modules of a site from the template that comes with the distribution
 * (`config/modules.json.dist`) by copying it to the site's own file. Only the installation reads the template;
 * the running system never does.
 *
 * @psalm-api Wired in through the DI configuration.
 */
final readonly class FileDefaultModules implements DefaultModules
{
    private Files $files;

    private FileLock $lock;

    public function __construct(
        private string $templateFile,
        private string $file,
        private string $lockFile,
    ) {
        $this->files = new Files();
        $this->lock = new FileLock($lockFile);
    }

    #[Override]
    public function provide(): void
    {
        try {
            $this->files->ensureDirectory(dirname($this->lockFile));
            $this->files->ensureDirectory(dirname($this->file));
            $this->lock->exclusive(function (): void {
                if (is_file($this->file) || !is_file($this->templateFile)) {
                    return;
                }

                $this->files->writeAtomically($this->file, $this->files->read($this->templateFile));
            });
        } catch (StorageIoFailure $storageIoFailure) {
            throw ModuleSettingsFailed::whileSaving($storageIoFailure);
        }
    }
}
