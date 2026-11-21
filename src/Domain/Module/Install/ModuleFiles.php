<?php

declare(strict_types=1);

namespace Yef\Domain\Module\Install;

use Yef\Domain\Module\Install\Exception\ModuleFilesFailed;
use Yef\Domain\Module\Install\Exception\StagingNotFound;
use Yef\Domain\Module\ModuleName;

/**
 * Where the modules are kept, and where a module waits before it is confirmed.
 */
interface ModuleFiles
{
    /**
     * Can modules be added, replaced and removed? False when the folder of the modules is not writable.
     */
    public function canChange(): bool;

    public function isInstalled(ModuleName $module): bool;

    /**
     * Makes a new staging area and, along the way, throws away the ones that have waited for too long.
     *
     * @throws ModuleFilesFailed
     */
    public function newStaging(): StagingArea;

    /**
     * @throws StagingNotFound When there is no such area: its token is wrong, or it was thrown away as too old.
     */
    public function staging(string $token): StagingArea;

    /**
     * @throws ModuleFilesFailed
     */
    public function remember(StagingArea $area, StagedOffer $offer): void;

    /**
     * @throws StagingNotFound When nothing was remembered for the area.
     */
    public function offerOf(StagingArea $area): StagedOffer;

    /**
     * Throws away a staging area with everything in it. Does nothing when it is already gone.
     */
    public function discard(StagingArea $area): void;

    /**
     * Puts the unpacked module of a staging area into its place as a new module, and throws the area away.
     *
     * @throws ModuleFilesFailed When the module is already there or cannot be moved.
     */
    public function install(StagingArea $area, ModuleName $module): void;

    /**
     * Replaces an installed module with the unpacked one of a staging area, and throws the area away.
     * When the replacement fails halfway, the module that was there stays as it was.
     *
     * @throws ModuleFilesFailed
     */
    public function replace(StagingArea $area, ModuleName $module): void;

    /**
     * @throws ModuleFilesFailed
     */
    public function remove(ModuleName $module): void;
}
