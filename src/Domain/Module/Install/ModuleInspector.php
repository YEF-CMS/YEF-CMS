<?php

declare(strict_types=1);

namespace Yef\Domain\Module\Install;

use Yef\Domain\Module\Install\Exception\InvalidModule;

/**
 * Reads the manifests of modules.
 */
interface ModuleInspector
{
    /**
     * The module in a directory that is not in its place yet (a freshly unpacked one).
     *
     * @throws InvalidModule When the module has no usable manifest.
     */
    public function inspect(string $directory): ModuleInfo;

    /**
     * Every module installed now whose manifest can be read.
     *
     * @return list<ModuleInfo>
     */
    public function installed(): array;
}
