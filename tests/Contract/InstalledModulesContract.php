<?php

declare(strict_types=1);

namespace Yef\Tests\Contract;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Yef\Domain\Module\Install\GitHubRepository;
use Yef\Domain\Module\Install\InstalledModule;
use Yef\Domain\Module\Install\InstalledModules;
use Yef\Domain\Module\Install\ReleaseVersion;
use Yef\Domain\Module\ModuleName;

/**
 * Behavior every InstalledModules implementation has to provide.
 */
abstract class InstalledModulesContract extends TestCase
{
    public function testNothingIsInstalledAtFirst(): void
    {
        $this->assertSame([], $this->createInstalled()->all());
        $this->assertNull($this->createInstalled()->find(new ModuleName('acme/gallery')));
    }

    public function testRecordedModuleCanBeFoundWithWhereItCameFrom(): void
    {
        $installed = $this->createInstalled();

        $installed->record($this->module('acme/gallery', '1.2.0'));

        $found = $installed->find(new ModuleName('acme/gallery'));
        $this->assertInstanceOf(InstalledModule::class, $found);
        $this->assertSame('acme/gallery', $found->repository->__toString());
        $this->assertSame('1.2.0', $found->version->value);
        $this->assertSame(hash('sha256', '1.2.0'), $found->archiveHash);
        $this->assertSame('2026-11-21T10:00:00+00:00', $found->installedAt->format(DATE_ATOM));
    }

    public function testRecordingAgainReplacesWhatWasRecorded(): void
    {
        $installed = $this->createInstalled();
        $installed->record($this->module('acme/gallery', '1.0.0'));

        $installed->record($this->module('acme/gallery', '1.1.0'));

        $this->assertCount(1, $installed->all());
        $this->assertSame('1.1.0', $installed->find(new ModuleName('acme/gallery'))?->version->value);
    }

    public function testModulesAreListedInTheOrderOfTheirNames(): void
    {
        $installed = $this->createInstalled();
        $installed->record($this->module('acme/zebra', '1.0.0'));
        $installed->record($this->module('acme/apple', '1.0.0'));

        $this->assertSame(
            ['acme/apple', 'acme/zebra'],
            array_map(static fn(InstalledModule $module): string => $module->name->value, $installed->all()),
        );
    }

    public function testForgottenModuleIsGoneAndOthersStay(): void
    {
        $installed = $this->createInstalled();
        $installed->record($this->module('acme/gallery', '1.0.0'));
        $installed->record($this->module('acme/news', '1.0.0'));

        $installed->forget(new ModuleName('acme/gallery'));

        $this->assertNull($installed->find(new ModuleName('acme/gallery')));
        $this->assertInstanceOf(\Yef\Domain\Module\Install\InstalledModule::class, $installed->find(new ModuleName('acme/news')));
    }

    public function testForgettingAModuleThatIsNotThereChangesNothing(): void
    {
        $installed = $this->createInstalled();
        $installed->record($this->module('acme/news', '1.0.0'));

        $installed->forget(new ModuleName('acme/gallery'));

        $this->assertCount(1, $installed->all());
    }

    abstract protected function createInstalled(): InstalledModules;

    protected function module(string $name, string $version): InstalledModule
    {
        return new InstalledModule(
            new ModuleName($name),
            GitHubRepository::fromAddress('https://github.com/' . $name),
            new ReleaseVersion($version),
            hash('sha256', $version),
            new DateTimeImmutable('2026-11-21T10:00:00+00:00'),
        );
    }
}
