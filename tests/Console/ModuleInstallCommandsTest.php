<?php

declare(strict_types=1);

namespace Yef\Tests\Console;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Yef\Console\CheckModuleUpdatesCommand;
use Yef\Console\InstallModuleCommand;
use Yef\Console\ModuleOfferPrinter;
use Yef\Console\RemoveModuleCommand;
use Yef\Console\UpdateModuleCommand;
use Yef\Domain\Module\ModuleName;
use Yef\Tests\Support\ModuleInstallScenario;

#[CoversClass(InstallModuleCommand::class)]
#[CoversClass(UpdateModuleCommand::class)]
#[CoversClass(RemoveModuleCommand::class)]
#[CoversClass(CheckModuleUpdatesCommand::class)]
#[CoversClass(ModuleOfferPrinter::class)]
final class ModuleInstallCommandsTest extends TestCase
{
    private ModuleInstallScenario $site;

    #[Override]
    protected function setUp(): void
    {
        $this->site = new ModuleInstallScenario();
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->site->cleanUp();
    }

    public function testInstallShowsTheModuleAndInstallsItWhenConfirmed(): void
    {
        $this->site->publish('acme/news', '1.2.0');

        $tester = $this->install();
        $tester->setInputs(['yes']);
        $tester->execute(['address' => 'https://github.com/acme/news']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('acme/news', $tester->getDisplay());
        $this->assertStringContainsString('1.2.0', $tester->getDisplay());
        $this->assertStringContainsString('runs on your site with full rights', $tester->getDisplay());
        $this->assertStringContainsString('yef:module:enable acme/news', $tester->getDisplay());
        $this->assertDirectoryExists($this->site->modules() . '/acme__news');
    }

    public function testInstallDoesNothingWithoutConfirmation(): void
    {
        $this->site->publish('acme/news', '1.2.0');

        $tester = $this->install();
        $tester->setInputs(['no']);
        $tester->execute(['address' => 'https://github.com/acme/news']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Nothing was installed.', $tester->getDisplay());
        $this->assertDirectoryDoesNotExist($this->site->modules() . '/acme__news');
    }

    public function testInstallWithYesDoesNotAsk(): void
    {
        $this->site->publish('acme/news', '1.2.0');

        $tester = $this->install();
        $tester->execute(['address' => 'https://github.com/acme/news', '--yes' => true], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertDirectoryExists($this->site->modules() . '/acme__news');
    }

    public function testInstallExplainsAWrongAddressAndAModuleWithProblems(): void
    {
        $this->site->publish('acme/future', '1.0.0', manifest: ['requires' => ['yef' => '^9.0']]);

        $wrong = $this->install();
        $wrong->execute(['address' => 'https://evil.example/x', '--yes' => true]);

        $problem = $this->install();
        $problem->execute(['address' => 'https://github.com/acme/future', '--yes' => true]);

        $this->assertSame(Command::FAILURE, $wrong->getStatusCode());
        $this->assertStringContainsString('is not an address of the form', $wrong->getDisplay());
        $this->assertSame(Command::FAILURE, $problem->getStatusCode());
        $this->assertStringContainsString('incompatible_core', $problem->getDisplay());
        $this->assertDirectoryDoesNotExist($this->site->modules() . '/acme__future');
    }

    public function testUpdateReplacesTheModuleWhenThereIsANewerRelease(): void
    {
        $this->installed('acme/news', '1.0.0');
        $this->site->publish('acme/news', '1.1.0');

        $tester = new CommandTester(new UpdateModuleCommand($this->site->prepareUpdate, $this->site->update));
        $tester->execute(['name' => 'acme/news', '--yes' => true], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('acme/news is updated to 1.1.0.', $tester->getDisplay());
        $this->assertSame('1.1.0', $this->site->installed->find(new ModuleName('acme/news'))?->version->value);
    }

    public function testUpdateOfAModuleThatIsUpToDateIsNotAFailure(): void
    {
        $this->installed('acme/news', '1.0.0');

        $tester = new CommandTester(new UpdateModuleCommand($this->site->prepareUpdate, $this->site->update));
        $tester->execute(['name' => 'acme/news', '--yes' => true], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('up to date', $tester->getDisplay());
    }

    public function testUpdateOfAModuleThatIsNotFromTheInstallerFails(): void
    {
        $tester = new CommandTester(new UpdateModuleCommand($this->site->prepareUpdate, $this->site->update));
        $tester->execute(['name' => 'acme/news', '--yes' => true], ['interactive' => false]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('was not installed by the installer', $tester->getDisplay());
    }

    public function testRemoveDeletesTheModuleOnConfirmation(): void
    {
        $this->installed('acme/news', '1.0.0');

        $tester = new CommandTester(new RemoveModuleCommand($this->site->remove));
        $tester->setInputs(['yes']);
        $tester->execute(['name' => 'acme/news']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertDirectoryDoesNotExist($this->site->modules() . '/acme__news');
    }

    public function testRemoveKeepsTheModuleWithoutConfirmation(): void
    {
        $this->installed('acme/news', '1.0.0');

        $tester = new CommandTester(new RemoveModuleCommand($this->site->remove));
        $tester->setInputs(['no']);
        $tester->execute(['name' => 'acme/news']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertDirectoryExists($this->site->modules() . '/acme__news');
    }

    public function testRemoveRefusesAModuleThatIsNotFromTheInstaller(): void
    {
        mkdir($this->site->modules() . '/acme__news');

        $tester = new CommandTester(new RemoveModuleCommand($this->site->remove));
        $tester->execute(['name' => 'acme/news', '--yes' => true], ['interactive' => false]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertDirectoryExists($this->site->modules() . '/acme__news');
    }

    public function testUpdatesCommandListsWhatCanBeUpdated(): void
    {
        $this->installed('acme/news', '1.0.0');
        $this->installed('acme/blog', '2.0.0');
        $this->site->publish('acme/news', '1.1.0');

        $tester = new CommandTester(new CheckModuleUpdatesCommand($this->site->checkUpdates));
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('available: php bin/yef yef:module:update acme/news', $tester->getDisplay());
        $this->assertStringContainsString('up to date', $tester->getDisplay());
    }

    private function install(): CommandTester
    {
        return new CommandTester(new InstallModuleCommand($this->site->inspect, $this->site->install));
    }

    private function installed(string $repository, string $version): void
    {
        $this->site->publish($repository, $version);
        $this->site->install->handle($this->site->inspect->handle('https://github.com/' . $repository)->token);
    }
}
