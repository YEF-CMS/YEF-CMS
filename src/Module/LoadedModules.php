<?php

declare(strict_types=1);

namespace Yef\Module;

use Yef\Domain\ContentType\ContentTypeDefinition;
use Yef\Domain\Module\ModuleStatus;

/**
 * Result of loading: what the enabled modules added and the state of every installed module.
 */
final readonly class LoadedModules
{
    /**
     * @param list<ModuleStatus> $statuses
     * @param list<ContentTypeDefinition> $contentTypes
     */
    public function __construct(
        public array $statuses,
        public array $contentTypes,
    ) {}
}
