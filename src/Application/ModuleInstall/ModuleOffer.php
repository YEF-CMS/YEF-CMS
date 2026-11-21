<?php

declare(strict_types=1);

namespace Yef\Application\ModuleInstall;

use Yef\Domain\Module\Install\InstalledModule;
use Yef\Domain\Module\Install\ModuleInfo;
use Yef\Domain\Module\Install\ModuleRelease;

use function count;

/**
 * A module that was downloaded and waits for the admin to confirm it.
 *
 * @psalm-api Read by the admin and console adapters.
 */
final readonly class ModuleOffer
{
    /**
     * @param string $token Names the downloaded module; given back to confirm it.
     * @param ?InstalledModule $installed What is installed now, when this offer replaces it.
     * @param list<OfferProblem> $problems
     */
    public function __construct(
        public string $token,
        public ModuleRelease $release,
        public ModuleInfo $info,
        public ?InstalledModule $installed,
        public array $problems,
    ) {}

    public function isUpdate(): bool
    {
        return $this->installed instanceof InstalledModule;
    }

    public function canBeApplied(): bool
    {
        return count($this->problems) === 0;
    }
}
