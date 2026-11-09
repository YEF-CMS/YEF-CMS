<?php

declare(strict_types=1);

namespace Yef\Domain\Module;

enum ModuleState: string
{
    /** Enabled and registered. */
    case Loaded = 'loaded';

    /** Installed and fine, but not enabled. */
    case Disabled = 'disabled';

    /** Cannot be used; the reason says why. */
    case Failed = 'failed';
}
