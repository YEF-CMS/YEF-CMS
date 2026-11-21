<?php

declare(strict_types=1);

namespace Yef\Tests\Contract;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Yef\Domain\Module\Install\InstalledModules;
use Yef\Tests\Support\InMemoryInstalledModules;

#[CoversClass(InMemoryInstalledModules::class)]
final class InMemoryInstalledModulesTest extends InstalledModulesContract
{
    #[Override]
    protected function createInstalled(): InstalledModules
    {
        return new InMemoryInstalledModules();
    }
}
