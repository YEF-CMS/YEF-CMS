<?php

declare(strict_types=1);

namespace Yef\Domain\Module;

use InvalidArgumentException;
use Yef\Domain\ContentType\ContentTypeDefinition;
use Yef\Domain\Text\Texts;

/**
 * What a module can add to the system. More kinds of additions (blocks, routes, events) will get their own methods.
 */
interface ModuleContext
{
    /**
     * The texts of this module in the language of the site: `$context->texts()->t('type.title')`.
     * They come from `messages/<language>/<directory of the module>.php` in the module's own directory.
     */
    public function texts(): Texts;

    /**
     * @throws InvalidArgumentException When this module has already added a type with the same id.
     */
    public function addContentType(ContentTypeDefinition $definition): void;
}
