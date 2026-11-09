<?php

declare(strict_types=1);

namespace Yef\Domain\Module;

use InvalidArgumentException;
use Yef\Domain\ContentType\ContentTypeDefinition;

/**
 * What a module can add to the system. More kinds of additions (blocks, routes, events) will get their own methods.
 */
interface ModuleContext
{
    /**
     * @throws InvalidArgumentException When this module has already added a type with the same id.
     */
    public function addContentType(ContentTypeDefinition $definition): void;
}
