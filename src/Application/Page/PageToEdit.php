<?php

declare(strict_types=1);

namespace Yef\Application\Page;

use Yef\Domain\ContentType\ContentTypeDefinition;
use Yef\Domain\Page\Page;

/**
 * A page together with what is needed to edit it.
 */
final readonly class PageToEdit
{
    /**
     * @param ContentTypeDefinition|null $type Null when the module of the page's type is not enabled: such a page can
     *                                         be seen and deleted, but not edited.
     */
    public function __construct(
        public Page $page,
        public ?ContentTypeDefinition $type,
    ) {}

    public function canBeEdited(): bool
    {
        return $this->type instanceof ContentTypeDefinition;
    }
}
