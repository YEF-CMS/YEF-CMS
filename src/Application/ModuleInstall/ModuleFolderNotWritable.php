<?php

declare(strict_types=1);

namespace Yef\Application\ModuleInstall;

use RuntimeException;

/**
 * The folder of the modules cannot be written to, so nothing is downloaded: it could not be put anywhere.
 */
final class ModuleFolderNotWritable extends RuntimeException
{
    public static function create(): self
    {
        return new self('The folder of the modules is not writable.');
    }
}
