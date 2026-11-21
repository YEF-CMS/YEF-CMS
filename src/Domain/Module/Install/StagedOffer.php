<?php

declare(strict_types=1);

namespace Yef\Domain\Module\Install;

use Yef\Domain\Module\ModuleName;

/**
 * What is remembered about a module that waits in a staging area.
 */
final readonly class StagedOffer
{
    /**
     * @param string $archiveHash SHA-256 of the downloaded archive.
     * @param ?ModuleName $replaces The installed module this one would replace; null when it would be a new module.
     */
    public function __construct(
        public ModuleRelease $release,
        public string $archiveHash,
        public ?ModuleName $replaces,
    ) {}

    public function isUpdate(): bool
    {
        return $this->replaces instanceof ModuleName;
    }
}
