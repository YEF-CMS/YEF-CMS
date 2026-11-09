<?php

declare(strict_types=1);

namespace Yef\Tests\Support;

use Override;
use Yef\Domain\Module\ModuleCatalog;
use Yef\Domain\Module\ModuleStatus;

final readonly class InMemoryModuleCatalog implements ModuleCatalog
{
    /**
     * @param list<ModuleStatus> $statuses
     */
    public function __construct(private array $statuses) {}

    #[Override]
    public function statuses(): array
    {
        return $this->statuses;
    }
}
