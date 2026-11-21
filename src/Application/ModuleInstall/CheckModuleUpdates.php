<?php

declare(strict_types=1);

namespace Yef\Application\ModuleInstall;

use Yef\Domain\Module\Install\Exception\InvalidReleaseVersion;
use Yef\Domain\Module\Install\Exception\NoRelease;
use Yef\Domain\Module\Install\Exception\RepositoryNotFound;
use Yef\Domain\Module\Install\Exception\SourceUnavailable;
use Yef\Domain\Module\Install\InstalledModule;
use Yef\Domain\Module\Install\InstalledModules;
use Yef\Domain\Module\Install\ModuleSource;

use function array_map;

/**
 * Asks GitHub for the latest release of every module the installer brought and compares its version with the
 * installed one. Nothing else is compared: no changelog, no other versions.
 *
 * @psalm-api Used by the admin and console adapters.
 */
final readonly class CheckModuleUpdates
{
    public function __construct(
        private InstalledModules $installed,
        private ModuleSource $source,
    ) {}

    /**
     * @return list<UpdateCheck> One for each installed module, in the order of their names.
     */
    public function handle(): array
    {
        return array_map($this->check(...), $this->installed->all());
    }

    private function check(InstalledModule $module): UpdateCheck
    {
        try {
            return new UpdateCheck($module, $this->source->latestRelease($module->repository)->version, null);
        } catch (RepositoryNotFound) {
            return new UpdateCheck($module, null, UpdateCheckFailure::RepositoryGone);
        } catch (NoRelease) {
            return new UpdateCheck($module, null, UpdateCheckFailure::NoRelease);
        } catch (InvalidReleaseVersion) {
            return new UpdateCheck($module, null, UpdateCheckFailure::BadVersion);
        } catch (SourceUnavailable $unavailable) {
            return new UpdateCheck(
                $module,
                null,
                $unavailable->reason === SourceUnavailable::RATE_LIMITED ? UpdateCheckFailure::RateLimited : UpdateCheckFailure::Unreachable,
            );
        }
    }
}
