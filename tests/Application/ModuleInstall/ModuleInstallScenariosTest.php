<?php

declare(strict_types=1);

namespace Yef\Tests\Application\ModuleInstall;

use DateTimeImmutable;
use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Yef\Application\ModuleInstall\CheckModuleUpdates;
use Yef\Application\ModuleInstall\GetModuleOffer;
use Yef\Application\ModuleInstall\InspectModule;
use Yef\Application\ModuleInstall\InstallModule;
use Yef\Application\ModuleInstall\ListInstalledModules;
use Yef\Application\ModuleInstall\ModuleFolderNotWritable;
use Yef\Application\ModuleInstall\ModuleIsUpToDate;
use Yef\Application\ModuleInstall\ModuleOffer;
use Yef\Application\ModuleInstall\ModuleOffers;
use Yef\Application\ModuleInstall\NotInstalledByInstaller;
use Yef\Application\ModuleInstall\OfferCannotBeApplied;
use Yef\Application\ModuleInstall\OfferProblem;
use Yef\Application\ModuleInstall\OfferProblemKind;
use Yef\Application\ModuleInstall\PrepareModuleUpdate;
use Yef\Application\ModuleInstall\RemoveModule;
use Yef\Application\ModuleInstall\UpdateCheck;
use Yef\Application\ModuleInstall\UpdateCheckFailure;
use Yef\Application\ModuleInstall\UpdateModule;
use Yef\Domain\Module\Install\Exception\InvalidModule;
use Yef\Domain\Module\Install\Exception\InvalidReleaseVersion;
use Yef\Domain\Module\Install\Exception\InvalidRepositoryAddress;
use Yef\Domain\Module\Install\Exception\NoRelease;
use Yef\Domain\Module\Install\Exception\RepositoryNotFound;
use Yef\Domain\Module\Install\Exception\SourceUnavailable;
use Yef\Domain\Module\Install\Exception\StagingNotFound;
use Yef\Domain\Module\Install\Exception\UnsafeArchive;
use Yef\Domain\Module\Install\GitHubRepository;
use Yef\Domain\Module\Install\InstalledModule;
use Yef\Domain\Module\Install\ReleaseVersion;
use Yef\Domain\Module\ModuleName;
use Yef\Tests\Support\DirectoryListing;
use Yef\Tests\Support\ModuleInstallScenario;
use Yef\Tests\Support\ModuleZip;
use Yef\Tests\Support\ThrowingInstalledModules;

#[CoversClass(InspectModule::class)]
#[CoversClass(GetModuleOffer::class)]
#[CoversClass(InstallModule::class)]
#[CoversClass(PrepareModuleUpdate::class)]
#[CoversClass(UpdateModule::class)]
#[CoversClass(RemoveModule::class)]
#[CoversClass(CheckModuleUpdates::class)]
#[CoversClass(ListInstalledModules::class)]
#[CoversClass(ModuleOffers::class)]
#[CoversClass(ModuleOffer::class)]
#[CoversClass(OfferProblem::class)]
#[CoversClass(OfferCannotBeApplied::class)]
#[CoversClass(ModuleFolderNotWritable::class)]
#[CoversClass(ModuleIsUpToDate::class)]
#[CoversClass(NotInstalledByInstaller::class)]
#[CoversClass(UpdateCheck::class)]
final class ModuleInstallScenariosTest extends TestCase
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

    // Looking at a module

    public function testInspectingShowsTheLatestReleaseAndInstallsNothing(): void
    {
        $this->site->publish('acme/news', '1.2.0');

        $offer = $this->site->inspect->handle($this->site->address('acme/news'));

        $this->assertSame('acme/news', $offer->info->name->value);
        $this->assertSame('Module acme/news', $offer->info->title);
        $this->assertSame('1.2.0', $offer->release->version->value);
        $this->assertSame('acme/news', (string) $offer->release->repository);
        $this->assertFalse($offer->isUpdate());
        $this->assertTrue($offer->canBeApplied());
        $this->assertDirectoryDoesNotExist($this->site->modules() . '/acme__news');
        $this->assertSame([], $this->site->installed->all());
        $this->assertSame(32, strlen($offer->token));
    }

    public function testInspectingAcceptsTheAddressInAnyOfItsForms(): void
    {
        $this->site->publish('acme/news', '1.0.0');

        $offer = $this->site->inspect->handle(" https://github.com/acme/news.git/\n");

        $this->assertSame('acme/news', $offer->info->name->value);
    }

    public function testInspectingAddressThatIsNotAGithubRepositoryAsksNobody(): void
    {
        try {
            $this->site->inspect->handle('https://evil.example/acme/news');
            $this->fail('The address had to be refused.');
        } catch (InvalidRepositoryAddress) {
            $this->assertSame(0, $this->site->source->latestReleaseCalls);
        }
    }

    public function testRepositoryThatDoesNotExistIsReported(): void
    {
        $this->site->source->fail('acme/news', RepositoryNotFound::named(GitHubRepository::fromAddress($this->site->address('acme/news'))));

        $this->expectException(RepositoryNotFound::class);

        $this->site->inspect->handle($this->site->address('acme/news'));
    }

    public function testRepositoryWithoutAReleaseIsReportedAndNothingIsDownloaded(): void
    {
        $this->site->source->fail('acme/news', NoRelease::in(GitHubRepository::fromAddress($this->site->address('acme/news'))));

        try {
            $this->site->inspect->handle($this->site->address('acme/news'));
            $this->fail('A repository without a release cannot be installed from.');
        } catch (NoRelease) {
            $this->assertSame(0, $this->site->source->downloads);
            $this->assertSame([], $this->site->waiting());
        }
    }

    public function testReleaseThatIsNotNamedAfterAVersionIsReported(): void
    {
        $this->site->source->fail('acme/news', new InvalidReleaseVersion('"latest" is not a semantic version.'));

        $this->expectException(InvalidReleaseVersion::class);

        $this->site->inspect->handle($this->site->address('acme/news'));
    }

    public function testUnavailableSourceIsReported(): void
    {
        $this->site->source->fail('acme/news', SourceUnavailable::because(SourceUnavailable::RATE_LIMITED));

        $this->expectException(SourceUnavailable::class);

        $this->site->inspect->handle($this->site->address('acme/news'));
    }

    public function testUnsafeArchiveIsRefusedAndNothingWaits(): void
    {
        $zip = ModuleZip::create($this->site->directory->path . '/bad.zip', ['x/module.json' => '{}', '../evil.php' => 'x']);
        $this->site->source->release('acme/news', '1.0.0', $zip);

        try {
            $this->site->inspect->handle($this->site->address('acme/news'));
            $this->fail('The archive had to be refused.');
        } catch (UnsafeArchive) {
            $this->assertSame([], $this->site->waiting());
            $this->assertFileDoesNotExist($this->site->directory->path . '/evil.php');
        }
    }

    public function testArchiveWithoutAManifestIsNotAModuleAndNothingWaits(): void
    {
        $zip = ModuleZip::create($this->site->directory->path . '/plain.zip', ['repo-1/README.md' => 'hello']);
        $this->site->source->release('acme/news', '1.0.0', $zip);

        try {
            $this->site->inspect->handle($this->site->address('acme/news'));
            $this->fail('A repository without a manifest is not a module.');
        } catch (InvalidModule $invalidModule) {
            $this->assertStringContainsString('module.json', $invalidModule->getMessage());
            $this->assertSame([], $this->site->waiting());
        }
    }

    public function testNothingIsDownloadedWhenTheFolderOfTheModulesCannotBeWrittenTo(): void
    {
        $this->site->publish('acme/news', '1.0.0');
        rmdir($this->site->modules());

        try {
            $this->site->inspect->handle($this->site->address('acme/news'));
            $this->fail('There is nowhere to put the module.');
        } catch (ModuleFolderNotWritable) {
            $this->assertSame(0, $this->site->source->downloads);
        }
    }

    public function testModuleForAnotherVersionOfTheCoreIsShownWithTheReason(): void
    {
        $this->site->publish('acme/future', '1.0.0', manifest: ['requires' => ['yef' => '^9.0']]);

        $offer = $this->site->inspect->handle($this->site->address('acme/future'));

        $this->assertFalse($offer->canBeApplied());
        $this->assertSame(OfferProblemKind::IncompatibleCore, $this->at($offer->problems, 0)->kind);
        $this->assertSame('^9.0', $this->at($offer->problems, 0)->detail);
    }

    public function testModuleThatIsAlreadyThereIsShownAsSuchEvenWhenPutThereByHand(): void
    {
        mkdir($this->site->modules() . '/acme__news');
        $this->site->publish('acme/news', '1.0.0');

        $offer = $this->site->inspect->handle($this->site->address('acme/news'));

        $this->assertSame([OfferProblemKind::AlreadyInstalled], $this->kinds($offer));
    }

    public function testNamespaceThatOverlapsWithTheCoreIsRefused(): void
    {
        $this->site->publish('acme/news', '1.0.0', manifest: ['namespace' => 'Yef\\Evil\\', 'entry' => 'Yef\\Evil\\Module']);

        $offer = $this->site->inspect->handle($this->site->address('acme/news'));

        $this->assertSame([OfferProblemKind::NamespaceTaken], $this->kinds($offer));
        $this->assertSame('core', $this->at($offer->problems, 0)->detail);
    }

    public function testNamespaceThatOverlapsWithAnotherModuleIsRefused(): void
    {
        $this->installByHand('acme/other', 'Shared\\Stuff\\');
        $this->site->publish('acme/news', '1.0.0', manifest: ['namespace' => 'Shared\\', 'entry' => 'Shared\\Module']);

        $offer = $this->site->inspect->handle($this->site->address('acme/news'));

        $this->assertSame([OfferProblemKind::NamespaceTaken], $this->kinds($offer));
        $this->assertSame('acme/other', $this->at($offer->problems, 0)->detail);
    }

    public function testShowingAWaitingModuleAgainWorksOutItsProblemsAnew(): void
    {
        $this->site->publish('acme/news', '1.0.0');
        $offer = $this->site->inspect->handle($this->site->address('acme/news'));
        $this->assertTrue($offer->canBeApplied());

        mkdir($this->site->modules() . '/acme__news');

        $again = $this->site->getOffer->handle($offer->token);

        $this->assertSame([OfferProblemKind::AlreadyInstalled], $this->kinds($again));
    }

    public function testShowingAModuleThatIsNotWaitingFails(): void
    {
        $this->expectException(StagingNotFound::class);

        $this->site->getOffer->handle(str_repeat('a', 32));
    }

    // Installing

    public function testConfirmingInstallsTheModuleWithoutEnablingIt(): void
    {
        $this->site->publish('acme/news', '1.2.0', extraFiles: ['templates/a.twig' => 'hello']);
        $offer = $this->site->inspect->handle($this->site->address('acme/news'));

        $record = $this->site->install->handle($offer->token);

        $this->assertFileExists($this->site->modules() . '/acme__news/module.json');
        $this->assertSame('hello', file_get_contents($this->site->modules() . '/acme__news/templates/a.twig'));
        $this->assertSame('acme/news', $record->name->value);
        $this->assertSame('1.2.0', $record->version->value);
        $this->assertSame('acme/news', (string) $record->repository);
        $this->assertSame(64, strlen($record->archiveHash));
        $this->assertSame([], $this->site->enabled->all());
        $this->assertSame([], $this->site->waiting());
        $this->assertEquals($record, $this->site->installed->find(new ModuleName('acme/news')));
    }

    public function testInstalledModulesAreListedWithTheirSource(): void
    {
        $this->site->publish('acme/news', '1.2.0');
        $this->site->install->handle($this->site->inspect->handle($this->site->address('acme/news'))->token);

        $listed = $this->site->list->handle();

        $this->assertCount(1, $listed);
        $this->assertSame('https://github.com/acme/news', $this->at($listed, 0)->repository->address());
    }

    public function testConfirmingTwiceInstallsOnce(): void
    {
        $this->site->publish('acme/news', '1.0.0');
        $offer = $this->site->inspect->handle($this->site->address('acme/news'));
        $this->site->install->handle($offer->token);

        $this->expectException(StagingNotFound::class);

        $this->site->install->handle($offer->token);
    }

    public function testConfirmingAModuleWithProblemsInstallsNothing(): void
    {
        $this->site->publish('acme/future', '1.0.0', manifest: ['requires' => ['yef' => '^9.0']]);
        $offer = $this->site->inspect->handle($this->site->address('acme/future'));

        try {
            $this->site->install->handle($offer->token);
            $this->fail('A module with problems must not be installed.');
        } catch (OfferCannotBeApplied $offerCannotBeApplied) {
            $this->assertSame(OfferProblemKind::IncompatibleCore, $this->at($offerCannotBeApplied->problems, 0)->kind);
            $this->assertDirectoryDoesNotExist($this->site->modules() . '/acme__future');
            $this->assertSame([], $this->site->installed->all());
        }
    }

    public function testAnOfferToUpdateCannotBeUsedToInstall(): void
    {
        $this->installFromGithub('acme/news', '1.0.0');
        $this->site->publish('acme/news', '1.1.0');
        $update = $this->site->prepareUpdate->handle('acme/news');

        $this->expectException(OfferCannotBeApplied::class);

        $this->site->install->handle($update->token);
    }

    public function testModuleIsRemovedAgainWhenItsSourceCannotBeRecorded(): void
    {
        $site = new ModuleInstallScenario(new ThrowingInstalledModules());
        try {
            $site->publish('acme/news', '1.0.0');
            $offer = $site->inspect->handle($site->address('acme/news'));

            try {
                $site->install->handle($offer->token);
                $this->fail('Recording had to fail.');
            } catch (\Yef\Domain\Module\Exception\ModuleSettingsFailed) {
                $this->assertDirectoryDoesNotExist($site->modules() . '/acme__news');
            }
        } finally {
            $site->cleanUp();
        }
    }

    // Updating

    public function testUpdatingShowsTheNewerReleaseAgainstWhatIsInstalled(): void
    {
        $this->installFromGithub('acme/news', '1.0.0');
        $this->site->publish('acme/news', '1.1.0');

        $offer = $this->site->prepareUpdate->handle('acme/news');

        $this->assertTrue($offer->isUpdate());
        $this->assertTrue($offer->canBeApplied());
        $this->assertSame('1.1.0', $offer->release->version->value);
        $this->assertSame('1.0.0', $offer->installed?->version->value);
        $this->assertFileExists($this->site->modules() . '/acme__news/module.json');
    }

    public function testConfirmingAnUpdateReplacesTheModuleAndRecordsTheNewVersion(): void
    {
        $this->installFromGithub('acme/news', '1.0.0', ['old-only.txt' => 'gone']);
        $this->site->publish('acme/news', '1.1.0', extraFiles: ['new.txt' => 'new']);
        $offer = $this->site->prepareUpdate->handle('acme/news');

        $record = $this->site->update->handle($offer->token);

        $this->assertSame('1.1.0', $record->version->value);
        $this->assertSame('1.1.0', $this->site->installed->find(new ModuleName('acme/news'))?->version->value);
        $this->assertFileExists($this->site->modules() . '/acme__news/new.txt');
        $this->assertFileDoesNotExist($this->site->modules() . '/acme__news/old-only.txt');
        $this->assertSame(['acme__news'], DirectoryListing::of($this->site->modules()));
        $this->assertSame([], $this->site->waiting());
    }

    public function testUpdatingKeepsTheModuleEnabledOrNot(): void
    {
        $this->installFromGithub('acme/news', '1.0.0');
        $this->site->enabled->enable(new ModuleName('acme/news'));
        $this->site->publish('acme/news', '1.1.0');

        $this->site->update->handle($this->site->prepareUpdate->handle('acme/news')->token);

        $this->assertEquals([new ModuleName('acme/news')], $this->site->enabled->all());
    }

    public function testUpdatingAModuleThatIsUpToDateDownloadsNothing(): void
    {
        $this->installFromGithub('acme/news', '1.0.0');
        $downloads = $this->site->source->downloads;

        try {
            $this->site->prepareUpdate->handle('acme/news');
            $this->fail('There is nothing newer.');
        } catch (ModuleIsUpToDate) {
            $this->assertSame($downloads, $this->site->source->downloads);
        }
    }

    public function testAnOlderReleaseIsNeverInstalledOverANewerModule(): void
    {
        $this->installFromGithub('acme/news', '2.0.0');
        $this->site->publish('acme/news', '1.5.0');

        $this->expectException(ModuleIsUpToDate::class);

        $this->site->prepareUpdate->handle('acme/news');
    }

    public function testOnlyModulesTheInstallerBroughtCanBeUpdated(): void
    {
        $this->installByHand('acme/news', 'ByHand\\');

        $this->expectException(NotInstalledByInstaller::class);

        $this->site->prepareUpdate->handle('acme/news');
    }

    public function testUpdateOfAModuleThatChangedItsNameIsRefusedAndTheModuleStaysAsItWas(): void
    {
        $this->installFromGithub('acme/news', '1.0.0');
        $this->site->publish('acme/news', '1.1.0', moduleName: 'acme/renamed');
        $offer = $this->site->prepareUpdate->handle('acme/news');

        $this->assertSame([OfferProblemKind::NameChanged], $this->kinds($offer));

        try {
            $this->site->update->handle($offer->token);
            $this->fail('A module that changed its name must not replace another one.');
        } catch (OfferCannotBeApplied) {
            $this->assertSame('1.0.0', $this->site->installed->find(new ModuleName('acme/news'))?->version->value);
            $this->assertDirectoryExists($this->site->modules() . '/acme__news');
            $this->assertDirectoryDoesNotExist($this->site->modules() . '/acme__renamed');
        }
    }

    public function testUpdateThatNeedsAnotherCoreIsShownWithTheReasonAndNotApplied(): void
    {
        $this->installFromGithub('acme/news', '1.0.0');
        $this->site->publish('acme/news', '2.0.0', manifest: ['requires' => ['yef' => '^9.0']]);
        $offer = $this->site->prepareUpdate->handle('acme/news');

        $this->assertSame([OfferProblemKind::IncompatibleCore], $this->kinds($offer));
        $this->expectException(OfferCannotBeApplied::class);

        $this->site->update->handle($offer->token);
    }

    public function testAnOfferToInstallCannotBeUsedToUpdate(): void
    {
        $this->site->publish('acme/news', '1.0.0');
        $offer = $this->site->inspect->handle($this->site->address('acme/news'));

        $this->expectException(OfferCannotBeApplied::class);

        $this->site->update->handle($offer->token);
    }

    // Checking for updates

    public function testCheckingFindsWhichModulesHaveNewerReleases(): void
    {
        $this->installFromGithub('acme/news', '1.0.0');
        $this->installFromGithub('acme/blog', '2.0.0');
        $this->site->publish('acme/news', '1.1.0');
        $this->site->publish('acme/blog', '2.0.0');

        $checks = $this->site->checkUpdates->handle();

        $this->assertSame(['acme/blog', 'acme/news'], array_map(static fn(UpdateCheck $check): string => $check->module->name->value, $checks));
        $this->assertFalse($this->at($checks, 0)->updateIsAvailable());
        $this->assertSame('2.0.0', $this->at($checks, 0)->latest?->value);
        $this->assertTrue($this->at($checks, 1)->updateIsAvailable());
        $this->assertSame('1.1.0', $this->at($checks, 1)->latest?->value);
        $this->assertNull($this->at($checks, 1)->failure);
    }

    public function testCheckingChangesNothing(): void
    {
        $this->installFromGithub('acme/news', '1.0.0');
        $this->site->publish('acme/news', '1.1.0');
        $downloads = $this->site->source->downloads;

        $this->site->checkUpdates->handle();

        $this->assertSame($downloads, $this->site->source->downloads);
        $this->assertSame('1.0.0', $this->site->installed->find(new ModuleName('acme/news'))?->version->value);
        $this->assertSame([], $this->site->waiting());
    }

    public function testCheckingSaysWhyAModuleCouldNotBeChecked(): void
    {
        $repository = GitHubRepository::fromAddress('https://github.com/acme/x');
        $cases = [
            'acme/gone' => [RepositoryNotFound::named($repository), UpdateCheckFailure::RepositoryGone],
            'acme/none' => [NoRelease::in($repository), UpdateCheckFailure::NoRelease],
            'acme/bad' => [new InvalidReleaseVersion('latest'), UpdateCheckFailure::BadVersion],
            'acme/limited' => [SourceUnavailable::because(SourceUnavailable::RATE_LIMITED), UpdateCheckFailure::RateLimited],
            'acme/down' => [SourceUnavailable::because(SourceUnavailable::UNREACHABLE), UpdateCheckFailure::Unreachable],
        ];
        foreach ($cases as $name => [$failure]) {
            $this->site->installed->record($this->record($name, '1.0.0'));
            $this->site->source->fail($name, $failure);
        }

        $failures = [];
        foreach ($this->site->checkUpdates->handle() as $check) {
            $failures[$check->module->name->value] = $check->failure;
            $this->assertNull($check->latest);
            $this->assertFalse($check->updateIsAvailable());
        }

        foreach ($cases as $name => [, $expected]) {
            $this->assertSame($expected, $failures[$name] ?? null, $name);
        }
    }

    public function testCheckingOneModuleDoesNotStopTheOthers(): void
    {
        $this->installFromGithub('acme/blog', '1.0.0');
        $this->installFromGithub('acme/news', '1.0.0');
        $this->site->source->fail('acme/blog', SourceUnavailable::because(SourceUnavailable::UNREACHABLE));
        $this->site->publish('acme/news', '1.1.0');

        $checks = $this->site->checkUpdates->handle();

        $this->assertSame(UpdateCheckFailure::Unreachable, $this->at($checks, 0)->failure);
        $this->assertTrue($this->at($checks, 1)->updateIsAvailable());
    }

    public function testNothingIsAskedWhenNothingWasInstalledByTheInstaller(): void
    {
        $this->assertSame([], $this->site->checkUpdates->handle());
        $this->assertSame(0, $this->site->source->latestReleaseCalls);
    }

    // Removing

    public function testRemovingDeletesTheModuleSwitchesItOffAndForgetsItsSource(): void
    {
        $this->installFromGithub('acme/news', '1.0.0');
        $this->installFromGithub('acme/blog', '1.0.0');
        $this->site->enabled->enable(new ModuleName('acme/news'));
        $this->site->enabled->enable(new ModuleName('acme/blog'));

        $this->site->remove->handle('acme/news');

        $this->assertDirectoryDoesNotExist($this->site->modules() . '/acme__news');
        $this->assertDirectoryExists($this->site->modules() . '/acme__blog');
        $this->assertEquals([new ModuleName('acme/blog')], $this->site->enabled->all());
        $this->assertNull($this->site->installed->find(new ModuleName('acme/news')));
        $this->assertInstanceOf(\Yef\Domain\Module\Install\InstalledModule::class, $this->site->installed->find(new ModuleName('acme/blog')));
    }

    public function testOnlyModulesTheInstallerBroughtAreRemoved(): void
    {
        $this->installByHand('acme/news', 'ByHand\\');
        $this->site->enabled->enable(new ModuleName('acme/news'));

        try {
            $this->site->remove->handle('acme/news');
            $this->fail('A module put there by hand must stay.');
        } catch (NotInstalledByInstaller) {
            $this->assertDirectoryExists($this->site->modules() . '/acme__news');
            $this->assertEquals([new ModuleName('acme/news')], $this->site->enabled->all());
        }
    }

    public function testRemovingSomethingThatIsNotAModuleNameIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->site->remove->handle('../etc');
    }

    // Helpers

    /**
     * @param array<string, string> $extraFiles
     */
    private function installFromGithub(string $repository, string $version, array $extraFiles = []): void
    {
        $this->site->publish($repository, $version, extraFiles: $extraFiles);
        $this->site->install->handle($this->site->inspect->handle($this->site->address($repository))->token);
    }

    /**
     * @template T
     *
     * @param list<T> $items
     *
     * @return T
     */
    private function at(array $items, int $index): mixed
    {
        return $items[$index] ?? $this->fail('There is no item #' . $index . '.');
    }

    private function installByHand(string $name, string $namespace): void
    {
        $directory = $this->site->modules() . '/' . new ModuleName($name)->directoryName();
        mkdir($directory . '/src', 0o777, true);
        file_put_contents($directory . '/module.json', json_encode([
            'schema' => 1,
            'name' => $name,
            'title' => 'By hand',
            'version' => '1.0.0',
            'namespace' => $namespace,
            'entry' => $namespace . 'Module',
            'requires' => ['yef' => '^0.1'],
        ], JSON_THROW_ON_ERROR));
    }

    private function record(string $name, string $version): InstalledModule
    {
        return new InstalledModule(
            new ModuleName($name),
            GitHubRepository::fromAddress('https://github.com/' . $name),
            new ReleaseVersion($version),
            hash('sha256', $name),
            new DateTimeImmutable('2026-11-21 10:00:00 UTC'),
        );
    }

    /**
     * @return list<OfferProblemKind>
     */
    private function kinds(ModuleOffer $offer): array
    {
        return array_map(static fn(OfferProblem $problem): OfferProblemKind => $problem->kind, $offer->problems);
    }
}
