<?php

declare(strict_types=1);

namespace Yef\Domain\ContentType;

use InvalidArgumentException;
use Yef\Domain\ContentType\Exception\UnknownContentType;

use function sprintf;

final readonly class ContentTypeRegistry
{
    /** @var array<string, ContentTypeDefinition> */
    private array $definitions;

    /**
     * @param list<ContentTypeDefinition> $definitions
     *
     * @throws InvalidArgumentException When two definitions have the same id.
     */
    public function __construct(array $definitions)
    {
        $indexed = [];
        foreach ($definitions as $definition) {
            if (isset($indexed[$definition->id->value])) {
                throw new InvalidArgumentException(
                    sprintf('Content type "%s" is registered twice.', $definition->id),
                );
            }

            $indexed[$definition->id->value] = $definition;
        }

        $this->definitions = $indexed;
    }

    /**
     * @throws UnknownContentType
     */
    public function get(ContentTypeId $id): ContentTypeDefinition
    {
        return $this->definitions[$id->value] ?? throw UnknownContentType::withId($id);
    }
}
