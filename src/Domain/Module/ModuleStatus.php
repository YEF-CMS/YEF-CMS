<?php

declare(strict_types=1);

namespace Yef\Domain\Module;

/**
 * What a user needs to know about an installed module.
 */
final readonly class ModuleStatus
{
    /**
     * @param string $id The module name, or the directory name when the manifest is unreadable.
     * @param string $title Human-readable name for screens ("Photo gallery"); the id when the manifest is unreadable.
     * @param string $reason Why the module failed; empty otherwise.
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $version,
        public ModuleState $state,
        public string $reason = '',
    ) {}
}
