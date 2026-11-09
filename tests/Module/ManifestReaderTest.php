<?php

declare(strict_types=1);

namespace Yef\Tests\Module;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yef\Module\InvalidManifest;
use Yef\Module\ManifestReader;
use Yef\Module\ModuleManifest;
use Yef\Tests\Support\ModuleFixtures;
use Yef\Tests\Support\TemporaryDirectory;

#[CoversClass(ManifestReader::class)]
#[CoversClass(ModuleManifest::class)]
#[CoversClass(InvalidManifest::class)]
final class ManifestReaderTest extends TestCase
{
    private TemporaryDirectory $directory;

    private ModuleFixtures $fixtures;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = new TemporaryDirectory();
        $this->fixtures = new ModuleFixtures($this->directory->path);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->directory->delete();
    }

    public function testReadsAValidManifest(): void
    {
        $directory = $this->fixtures->install('acme/photo-gallery', manifest: ['title' => 'Photo gallery', 'description' => 'Albums']);

        $manifest = new ManifestReader()->read($directory);

        $this->assertSame('acme/photo-gallery', $manifest->name->value);
        $this->assertSame('Photo gallery', $manifest->title);
        $this->assertSame('Albums', $manifest->description);
        $this->assertSame('1.0.0', $manifest->version);
        $this->assertSame('^0.1', $manifest->coreConstraint);
    }

    public function testTitleIsTrimmed(): void
    {
        $directory = $this->fixtures->install('acme/news', manifest: ['title' => '  News  ']);

        $this->assertSame('News', new ManifestReader()->read($directory)->title);
    }

    public function testSupportsOnlyMatchingCoreVersions(): void
    {
        $manifest = new ManifestReader()->read($this->fixtures->install('acme/news', manifest: ['requires' => ['yef' => '^0.2']]));

        $this->assertTrue($manifest->supports('0.2.5'));
        $this->assertFalse($manifest->supports('0.1.0'));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidManifests(): iterable
    {
        yield 'no title' => [['title' => null], 'title'];
        yield 'blank title' => [['title' => '   '], 'title'];
        yield 'too long title' => [['title' => str_repeat('a', 81)], 'title'];
        yield 'wrong schema' => [['schema' => 2], 'schema'];
        yield 'no schema' => [['schema' => null], 'schema'];
        yield 'bad name' => [['name' => 'Acme Gallery'], 'not a valid module name'];
        yield 'bad version' => [['version' => 'soon'], 'version'];
        yield 'no core constraint' => [['requires' => null], 'requires.yef'];
        yield 'bad core constraint' => [['requires' => ['yef' => 'banana']], 'requires.yef'];
        yield 'namespace without trailing backslash' => [['namespace' => 'Acme\\Gallery'], 'namespace'];
        yield 'entry outside the namespace' => [['entry' => 'Other\\Module'], 'entry'];
        yield 'description is not text' => [['description' => 5], 'description'];
    }

    /**
     * @param array<string, mixed> $changes
     */
    #[DataProvider('invalidManifests')]
    public function testRejectsAnInvalidManifest(array $changes, string $mentions): void
    {
        $directory = $this->fixtures->install('acme/news', manifest: $changes);

        $this->expectException(InvalidManifest::class);
        $this->expectExceptionMessage($mentions);

        new ManifestReader()->read($directory);
    }

    public function testRejectsADirectoryThatDoesNotMatchTheName(): void
    {
        $directory = $this->fixtures->install('acme/news', directory: 'something-else');

        $this->expectException(InvalidManifest::class);
        $this->expectExceptionMessage('acme__news');

        new ManifestReader()->read($directory);
    }

    public function testRejectsAMissingManifest(): void
    {
        $this->expectException(InvalidManifest::class);

        new ManifestReader()->read($this->directory->path . '/acme__news');
    }

    public function testRejectsBrokenJson(): void
    {
        $directory = $this->fixtures->install('acme/news');
        file_put_contents($directory . '/module.json', '{ not json');

        $this->expectException(InvalidManifest::class);
        $this->expectExceptionMessage('not valid JSON');

        new ManifestReader()->read($directory);
    }
}
