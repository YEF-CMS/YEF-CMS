<?php

declare(strict_types=1);

namespace Yef\Tests\Storage;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Yef\Domain\Module\ModuleName;
use Yef\Storage\FileDefaultModules;
use Yef\Storage\FileEnabledModules;
use Yef\Tests\Support\TemporaryDirectory;

#[CoversClass(FileDefaultModules::class)]
final class FileDefaultModulesTest extends TestCase
{
    private TemporaryDirectory $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = new TemporaryDirectory();
        file_put_contents($this->template(), "{\n    \"enabled\": [\n        \"acme/starter\"\n    ]\n}\n");
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->directory->delete();
    }

    public function testInstallationCopiesTheTemplateToTheSitesOwnList(): void
    {
        $this->defaults()->provide();

        $this->assertFileEquals($this->template(), $this->file());
        $this->assertEquals([new ModuleName('acme/starter')], $this->enabled()->all());
    }

    public function testTheTemplateItselfIsLeftAlone(): void
    {
        $before = (string) file_get_contents($this->template());

        $this->defaults()->provide();
        $this->enabled()->disable(new ModuleName('acme/starter'));

        $this->assertSame($before, (string) file_get_contents($this->template()));
    }

    public function testAListThatTheOwnerAlreadyHasIsNotReplaced(): void
    {
        mkdir(dirname($this->file()), 0o777, true);
        file_put_contents($this->file(), '{"enabled": ["acme/mine"]}');

        $this->defaults()->provide();

        $this->assertEquals([new ModuleName('acme/mine')], $this->enabled()->all());
    }

    public function testProvidingTwiceChangesNothing(): void
    {
        $this->defaults()->provide();
        $this->enabled()->enable(new ModuleName('acme/extra'));

        $this->defaults()->provide();

        $this->assertEquals([new ModuleName('acme/starter'), new ModuleName('acme/extra')], $this->enabled()->all());
    }

    public function testWithoutATemplateTheSiteStartsWithNothingEnabled(): void
    {
        unlink($this->template());

        $this->defaults()->provide();

        $this->assertFileDoesNotExist($this->file());
        $this->assertSame([], $this->enabled()->all());
    }

    private function defaults(): FileDefaultModules
    {
        return new FileDefaultModules($this->template(), $this->file(), $this->directory->path . '/var/modules.lock');
    }

    private function enabled(): FileEnabledModules
    {
        return new FileEnabledModules($this->file(), $this->directory->path . '/var/modules.lock');
    }

    private function template(): string
    {
        return $this->directory->path . '/modules.json.dist';
    }

    private function file(): string
    {
        return $this->directory->path . '/data/modules.json';
    }
}
