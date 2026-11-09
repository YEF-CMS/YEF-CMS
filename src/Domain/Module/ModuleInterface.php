<?php

declare(strict_types=1);

namespace Yef\Domain\Module;

/**
 * The entry point of a module: the one class the core instantiates (with no arguments) to let the module
 * tell what it adds. Named by `entry` in the manifest.
 */
interface ModuleInterface
{
    /**
     * Called once per request when the module is enabled. An exception disables the module for this request
     * and leaves nothing of it registered.
     */
    public function register(ModuleContext $context): void;
}
