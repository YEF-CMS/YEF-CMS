<?php

declare(strict_types=1);

namespace Yef\Application\ModuleInstall;

use Yef\Domain\Module\Install\InstalledModule;
use Yef\Domain\Module\Install\ReleaseVersion;

/**
 * What the latest release says about one installed module.
 *
 * @psalm-api Read by the admin and console adapters.
 */
final readonly class UpdateCheck
{
    /**
     * @param ?ReleaseVersion $latest The version of the latest release; null when it could not be found out.
     * @param ?UpdateCheckFailure $failure Why it could not be found out.
     */
    public function __construct(
        public InstalledModule $module,
        public ?ReleaseVersion $latest,
        public ?UpdateCheckFailure $failure,
    ) {}

    public function updateIsAvailable(): bool
    {
        return $this->latest instanceof ReleaseVersion && $this->latest->isNewerThan($this->module->version);
    }
}
