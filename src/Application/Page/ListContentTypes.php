<?php

declare(strict_types=1);

namespace Yef\Application\Page;

use Yef\Domain\ContentType\ContentTypeDefinition;
use Yef\Domain\ContentType\ContentTypeRegistry;

/**
 * The content types pages can be made of: those of the enabled modules.
 *
 * @psalm-api Used by the admin adapter.
 */
final readonly class ListContentTypes
{
    public function __construct(private ContentTypeRegistry $types) {}

    /**
     * @return list<ContentTypeDefinition>
     */
    public function handle(): array
    {
        return $this->types->all();
    }
}
