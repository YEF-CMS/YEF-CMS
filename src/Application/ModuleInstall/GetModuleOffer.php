<?php

declare(strict_types=1);

namespace Yef\Application\ModuleInstall;

use Yef\Domain\Module\Install\Exception\InvalidModule;
use Yef\Domain\Module\Install\Exception\StagingNotFound;
use Yef\Domain\Module\Install\ModuleFiles;
use Yef\Domain\Module\Install\ModuleInspector;

/**
 * Shows again a module that waits to be confirmed. What is wrong with it is worked out anew each time,
 * because the site may have changed since it was downloaded.
 *
 * @psalm-api Used by the admin adapter.
 */
final readonly class GetModuleOffer
{
    public function __construct(
        private ModuleFiles $files,
        private ModuleInspector $inspector,
        private ModuleOffers $offers,
    ) {}

    /**
     * @throws StagingNotFound
     * @throws InvalidModule
     */
    public function handle(string $token): ModuleOffer
    {
        $area = $this->files->staging($token);

        return $this->offers->build($area, $this->files->offerOf($area), $this->inspector->inspect($area->directory));
    }
}
