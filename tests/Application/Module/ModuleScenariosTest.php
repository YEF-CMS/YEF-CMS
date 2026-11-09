<?php

declare(strict_types=1);

namespace Yef\Tests\Application\Module;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Yef\Application\Module\DisableModule;
use Yef\Application\Module\EnableModule;
use Yef\Application\Module\ListModules;
use Yef\Domain\Module\Exception\ModuleCannotBeEnabled;
use Yef\Domain\Module\Exception\ModuleNotFound;
use Yef\Domain\Module\ModuleName;
use Yef\Domain\Module\ModuleState;
use Yef\Domain\Module\ModuleStatus;
use Yef\Tests\Support\InMemoryEnabledModules;
use Yef\Tests\Support\InMemoryModuleCatalog;

#[CoversClass(ListModules::class)]
#[CoversClass(EnableModule::class)]
#[CoversClass(DisableModule::class)]
final class ModuleScenariosTest extends TestCase
{
    public function testListsTheModulesOfTheCatalog(): void
    {
        $status = new ModuleStatus('acme/gallery', 'Photo gallery', '1.0.0', ModuleState::Disabled);

        $this->assertSame([$status], new ListModules(new InMemoryModuleCatalog([$status]))->handle());
    }

    public function testEnablesAnInstalledModule(): void
    {
        $enabled = new InMemoryEnabledModules();

        new EnableModule($this->catalog(ModuleState::Disabled), $enabled)->handle('acme/gallery');

        $this->assertEquals([new ModuleName('acme/gallery')], $enabled->all());
    }

    public function testRefusesAModuleThatIsNotInstalled(): void
    {
        $this->expectException(ModuleNotFound::class);

        new EnableModule($this->catalog(ModuleState::Disabled), new InMemoryEnabledModules())->handle('acme/news');
    }

    public function testRefusesABrokenModuleAndSaysWhy(): void
    {
        $enabled = new InMemoryEnabledModules();

        try {
            new EnableModule($this->catalog(ModuleState::Failed, 'Needs Yef ^9.0'), $enabled)->handle('acme/gallery');
            $this->fail('A broken module must not be enabled.');
        } catch (ModuleCannotBeEnabled $moduleCannotBeEnabled) {
            $this->assertStringContainsString('Needs Yef ^9.0', $moduleCannotBeEnabled->getMessage());
        }

        $this->assertSame([], $enabled->all());
    }

    public function testRejectsAnInvalidName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EnableModule($this->catalog(ModuleState::Disabled), new InMemoryEnabledModules())->handle('Gallery');
    }

    public function testDisablesAModule(): void
    {
        $enabled = new InMemoryEnabledModules(['acme/gallery', 'acme/news']);

        new DisableModule($enabled)->handle('acme/gallery');

        $this->assertEquals([new ModuleName('acme/news')], $enabled->all());
    }

    private function catalog(ModuleState $state, string $reason = ''): InMemoryModuleCatalog
    {
        return new InMemoryModuleCatalog([new ModuleStatus('acme/gallery', 'Photo gallery', '1.0.0', $state, $reason)]);
    }
}
