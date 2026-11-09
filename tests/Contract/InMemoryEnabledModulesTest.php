<?php

declare(strict_types=1);

namespace Yef\Tests\Contract;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Yef\Domain\Module\EnabledModules;
use Yef\Tests\Support\InMemoryEnabledModules;

#[CoversClass(InMemoryEnabledModules::class)]
final class InMemoryEnabledModulesTest extends EnabledModulesContract
{
    #[Override]
    protected function createEnabledModules(): EnabledModules
    {
        return new InMemoryEnabledModules();
    }
}
