<?php

declare(strict_types=1);

namespace Yef\Module;

use InvalidArgumentException;
use Override;
use Yef\Domain\ContentType\ContentTypeDefinition;
use Yef\Domain\Module\ModuleContext;
use Yef\Domain\Text\Texts;

use function array_values;
use function sprintf;

/**
 * Collects what one module adds. Nothing reaches the system until the module's registration has finished.
 */
final class CollectingModuleContext implements ModuleContext
{
    /** @var array<string, ContentTypeDefinition> */
    private array $contentTypes = [];

    public function __construct(private readonly Texts $texts) {}

    #[Override]
    public function texts(): Texts
    {
        return $this->texts;
    }

    #[Override]
    public function addContentType(ContentTypeDefinition $definition): void
    {
        if (isset($this->contentTypes[$definition->id->value])) {
            throw new InvalidArgumentException(sprintf('Content type "%s" is added twice.', $definition->id));
        }

        $this->contentTypes[$definition->id->value] = $definition;
    }

    /**
     * @return list<ContentTypeDefinition>
     */
    public function contentTypes(): array
    {
        return array_values($this->contentTypes);
    }
}
