<?php

declare(strict_types=1);

namespace Yef\Application\ModuleInstall;

enum OfferProblemKind: string
{
    /** The module needs other versions of the core (the detail is the constraint). */
    case IncompatibleCore = 'incompatible_core';

    /** A module with this name is installed already; update it instead. */
    case AlreadyInstalled = 'already_installed';

    /** The updated module has another name than the one installed (the detail is the new name). */
    case NameChanged = 'name_changed';

    /** The namespace of the module overlaps with the core or another module (the detail names it). */
    case NamespaceTaken = 'namespace_taken';

    /** The folder of the modules cannot be written to. */
    case FolderNotWritable = 'folder_not_writable';
}
