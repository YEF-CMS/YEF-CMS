<?php

declare(strict_types=1);

namespace Yef\Admin\Modules;

use InvalidArgumentException;
use Throwable;
use Yef\Application\ModuleInstall\ModuleFolderNotWritable;
use Yef\Application\ModuleInstall\ModuleIsUpToDate;
use Yef\Application\ModuleInstall\NotInstalledByInstaller;
use Yef\Application\ModuleInstall\OfferCannotBeApplied;
use Yef\Domain\Module\Exception\ModuleSettingsFailed;
use Yef\Domain\Module\Install\Exception\InvalidModule;
use Yef\Domain\Module\Install\Exception\InvalidReleaseVersion;
use Yef\Domain\Module\Install\Exception\InvalidRepositoryAddress;
use Yef\Domain\Module\Install\Exception\ModuleFilesFailed;
use Yef\Domain\Module\Install\Exception\NoRelease;
use Yef\Domain\Module\Install\Exception\RepositoryNotFound;
use Yef\Domain\Module\Install\Exception\SourceUnavailable;
use Yef\Domain\Module\Install\Exception\StagingNotFound;
use Yef\Domain\Module\Install\Exception\UnsafeArchive;

/**
 * Turns what went wrong while installing, updating or removing a module into a text of the admin.
 *
 * @psalm-api Used by the actions of the modules screens.
 */
final readonly class InstallFailures
{
    /**
     * @return ?array{key: string, params: array<string, string>} The text to show, or null when the failure is not
     *                                                            one the admin can explain and must not be hidden.
     */
    public function explain(Throwable $failure): ?array
    {
        $key = match (true) {
            $failure instanceof InvalidRepositoryAddress => 'address',
            $failure instanceof RepositoryNotFound => 'repository_not_found',
            $failure instanceof NoRelease => 'no_release',
            $failure instanceof InvalidReleaseVersion => 'bad_version',
            $failure instanceof SourceUnavailable => $failure->reason,
            $failure instanceof UnsafeArchive => 'archive.' . $failure->reason,
            $failure instanceof InvalidModule => 'invalid_module',
            $failure instanceof ModuleFolderNotWritable => 'not_writable',
            $failure instanceof ModuleFilesFailed => 'files_failed',
            $failure instanceof StagingNotFound => 'expired',
            $failure instanceof ModuleIsUpToDate => 'up_to_date',
            $failure instanceof NotInstalledByInstaller => 'not_installed_by_installer',
            $failure instanceof OfferCannotBeApplied => 'cannot_apply',
            default => null,
        };

        if ($key !== null) {
            return [
                'key' => 'modules.install.error.' . $key,
                'params' => $failure instanceof InvalidModule ? ['reason' => $failure->getMessage()] : [],
            ];
        }

        if ($failure instanceof ModuleSettingsFailed) {
            return ['key' => 'modules.error.settings', 'params' => []];
        }

        return $failure instanceof InvalidArgumentException ? ['key' => 'modules.install.error.address', 'params' => []] : null;
    }
}
