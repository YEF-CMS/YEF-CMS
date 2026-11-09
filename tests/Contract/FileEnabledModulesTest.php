<?php

declare(strict_types=1);

namespace Yef\Tests\Contract;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Yef\Domain\Module\EnabledModules;
use Yef\Domain\Module\ModuleName;
use Yef\Storage\FileEnabledModules;
use Yef\Tests\Support\TemporaryDirectory;

#[CoversClass(FileEnabledModules::class)]
final class FileEnabledModulesTest extends EnabledModulesContract
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

    public function testChoiceSurvivesANewInstance(): void
    {
        $this->createEnabledModules()->enable(new ModuleName('acme/gallery'));

        $this->assertEquals([new ModuleName('acme/gallery')], $this->createEnabledModules()->all());
    }

    public function testFileIsReadableJsonWithTheEnabledNames(): void
    {
        $this->createEnabledModules()->enable(new ModuleName('acme/gallery'));

        $this->assertSame(['enabled' => ['acme/gallery']], json_decode((string) file_get_contents($this->file()), true));
    }

    public function testDamagedFileReadsAsNothingEnabledAndCanBeWrittenAgain(): void
    {
        file_put_contents($this->file(), '{ nope');
        $enabled = $this->createEnabledModules();

        $this->assertSame([], $enabled->all());

        $enabled->enable(new ModuleName('acme/news'));
        $this->assertEquals([new ModuleName('acme/news')], $enabled->all());
    }

    public function testInvalidEntriesAreIgnored(): void
    {
        file_put_contents($this->file(), '{"enabled": ["acme/news", 5, "Not A Name"]}');

        $this->assertSame([], $this->createEnabledModules()->all());
    }

    #[Override]
    protected function createEnabledModules(): EnabledModules
    {
        return new FileEnabledModules($this->file(), $this->directory->path . '/var/modules.lock');
    }

    private function file(): string
    {
        return $this->directory->path . '/modules.json';
    }
}
