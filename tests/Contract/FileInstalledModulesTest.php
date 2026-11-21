<?php

declare(strict_types=1);

namespace Yef\Tests\Contract;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Yef\Domain\Module\Install\InstalledModules;
use Yef\Domain\Module\ModuleName;
use Yef\Storage\FileInstalledModules;
use Yef\Tests\Support\TemporaryDirectory;

#[CoversClass(FileInstalledModules::class)]
final class FileInstalledModulesTest extends InstalledModulesContract
{
    private TemporaryDirectory $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = new TemporaryDirectory();
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->directory->delete();
    }

    public function testWhatWasRecordedSurvivesANewInstance(): void
    {
        $this->createInstalled()->record($this->module('acme/gallery', '1.0.0'));

        $this->assertInstanceOf(\Yef\Domain\Module\Install\InstalledModule::class, $this->createInstalled()->find(new ModuleName('acme/gallery')));
    }

    public function testFileIsReadableJsonWithTheSource(): void
    {
        $this->createInstalled()->record($this->module('acme/gallery', '1.0.0'));

        /** @var array{modules: array<string, array<string, string>>} $data */
        $data = json_decode((string) file_get_contents($this->file()), true);
        $this->assertSame('acme/gallery', $data['modules']['acme/gallery']['repository'] ?? null);
        $this->assertSame('1.0.0', $data['modules']['acme/gallery']['version'] ?? null);
    }

    public function testDamagedFileReadsAsNothingInstalledAndCanBeWrittenAgain(): void
    {
        file_put_contents($this->file(), '{ nope');
        $installed = $this->createInstalled();

        $this->assertSame([], $installed->all());

        $installed->record($this->module('acme/news', '1.0.0'));
        $this->assertCount(1, $installed->all());
    }

    public function testInvalidEntriesAreIgnored(): void
    {
        file_put_contents($this->file(), (string) json_encode(['modules' => [
            'acme/good' => ['repository' => 'acme/good', 'version' => '1.0.0', 'archive' => 'x', 'installedAt' => '2026-11-21T10:00:00+00:00'],
            'Not A Name' => ['repository' => 'acme/x', 'version' => '1.0.0', 'archive' => 'x', 'installedAt' => '2026-11-21T10:00:00+00:00'],
            'acme/badversion' => ['repository' => 'acme/x', 'version' => 'latest', 'archive' => 'x', 'installedAt' => '2026-11-21T10:00:00+00:00'],
            'acme/badrepo' => ['repository' => '../../etc', 'version' => '1.0.0', 'archive' => 'x', 'installedAt' => '2026-11-21T10:00:00+00:00'],
            'acme/missing' => ['repository' => 'acme/x'],
            'acme/date' => ['repository' => 'acme/x', 'version' => '1.0.0', 'archive' => 'x', 'installedAt' => 'never'],
        ]]));

        $this->assertSame(['acme/good'], array_map(
            static fn(\Yef\Domain\Module\Install\InstalledModule $module): string => $module->name->value,
            $this->createInstalled()->all(),
        ));
    }

    #[Override]
    protected function createInstalled(): InstalledModules
    {
        return new FileInstalledModules($this->file(), $this->directory->path . '/var/installed.lock');
    }

    private function file(): string
    {
        return $this->directory->path . '/modules.lock.json';
    }
}
