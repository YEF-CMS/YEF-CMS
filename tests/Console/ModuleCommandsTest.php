<?php

declare(strict_types=1);

namespace Yef\Tests\Console;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Yef\Application\Module\DisableModule;
use Yef\Application\Module\EnableModule;
use Yef\Application\Module\ListModules;
use Yef\Console\DisableModuleCommand;
use Yef\Console\EnableModuleCommand;
use Yef\Console\ListModulesCommand;
use Yef\Domain\Module\ModuleName;
use Yef\Domain\Module\ModuleState;
use Yef\Domain\Module\ModuleStatus;
use Yef\Tests\Support\InMemoryEnabledModules;
use Yef\Tests\Support\InMemoryModuleCatalog;

#[CoversClass(ListModulesCommand::class)]
#[CoversClass(EnableModuleCommand::class)]
#[CoversClass(DisableModuleCommand::class)]
final class ModuleCommandsTest extends TestCase
{
    public function testListShowsTheTitleAndTheReason(): void
    {
        $catalog = new InMemoryModuleCatalog([
            new ModuleStatus('acme/gallery', 'Photo gallery', '1.0.0', ModuleState::Loaded),
            new ModuleStatus('acme/news', 'News', '2.0.0', ModuleState::Failed, 'Needs Yef ^9.0'),
        ]);

        $tester = new CommandTester(new ListModulesCommand(new ListModules($catalog)));
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Photo gallery', $tester->getDisplay());
        $this->assertStringContainsString('acme/gallery', $tester->getDisplay());
        $this->assertStringContainsString('Needs Yef ^9.0', $tester->getDisplay());
    }

    public function testEnableReportsSuccess(): void
    {
        $enabled = new InMemoryEnabledModules();
        $catalog = new InMemoryModuleCatalog([new ModuleStatus('acme/gallery', 'Photo gallery', '1.0.0', ModuleState::Disabled)]);

        $tester = new CommandTester(new EnableModuleCommand(new EnableModule($catalog, $enabled)));
        $tester->execute(['name' => 'acme/gallery']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertEquals([new ModuleName('acme/gallery')], $enabled->all());
    }

    public function testEnableFailsForAnUnknownModule(): void
    {
        $tester = new CommandTester(new EnableModuleCommand(new EnableModule(new InMemoryModuleCatalog([]), new InMemoryEnabledModules())));
        $tester->execute(['name' => 'acme/gallery']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('not installed', $tester->getDisplay());
    }

    public function testEnableFailsForAnInvalidName(): void
    {
        $tester = new CommandTester(new EnableModuleCommand(new EnableModule(new InMemoryModuleCatalog([]), new InMemoryEnabledModules())));
        $tester->execute(['name' => 'Gallery']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    public function testDisableReportsSuccess(): void
    {
        $enabled = new InMemoryEnabledModules(['acme/gallery']);

        $tester = new CommandTester(new DisableModuleCommand(new DisableModule($enabled)));
        $tester->execute(['name' => 'acme/gallery']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame([], $enabled->all());
    }

    public function testDisableFailsForAnInvalidName(): void
    {
        $tester = new CommandTester(new DisableModuleCommand(new DisableModule(new InMemoryEnabledModules())));
        $tester->execute(['name' => 'Gallery']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
    }
}
