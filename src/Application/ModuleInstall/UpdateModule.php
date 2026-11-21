<?php

declare(strict_types=1);

namespace Yef\Application\ModuleInstall;

use Yef\Domain\Clock;
use Yef\Domain\Module\Exception\ModuleSettingsFailed;
use Yef\Domain\Module\Install\Exception\InvalidModule;
use Yef\Domain\Module\Install\Exception\ModuleFilesFailed;
use Yef\Domain\Module\Install\Exception\StagingNotFound;
use Yef\Domain\Module\Install\InstalledModule;
use Yef\Domain\Module\Install\InstalledModules;
use Yef\Domain\Module\Install\ModuleFiles;

/**
 * Replaces an installed module with the downloaded newer one. It stays as enabled or disabled as it was.
 * Whether the person who asks may do it is for the caller to decide: this changes the code the site runs.
 *
 * @psalm-api Used by the admin and console adapters.
 */
final readonly class UpdateModule
{
    public function __construct(
        private GetModuleOffer $getOffer,
        private ModuleFiles $files,
        private InstalledModules $installed,
        private Clock $clock,
    ) {}

    /**
     * @throws StagingNotFound
     * @throws InvalidModule
     * @throws OfferCannotBeApplied
     * @throws ModuleFilesFailed
     * @throws ModuleSettingsFailed
     */
    public function handle(string $token): InstalledModule
    {
        $offer = $this->getOffer->handle($token);
        if (!$offer->isUpdate()) {
            throw OfferCannotBeApplied::forTheOtherAction();
        }

        if (!$offer->canBeApplied()) {
            throw OfferCannotBeApplied::because($offer->problems);
        }

        $area = $this->files->staging($token);
        $record = new InstalledModule(
            $offer->info->name,
            $offer->release->repository,
            $offer->release->version,
            $this->files->offerOf($area)->archiveHash,
            $this->clock->now(),
        );

        $this->files->replace($area, $offer->info->name);
        $this->installed->record($record);

        return $record;
    }
}
