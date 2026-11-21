<?php

declare(strict_types=1);

namespace Yef\Tests\Platform;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yef\Domain\Module\Install\Exception\UnsafeArchive;
use Yef\Platform\ZipModuleArchive;
use Yef\Tests\Support\DirectoryListing;
use Yef\Tests\Support\ModuleZip;
use Yef\Tests\Support\TemporaryDirectory;
use ZipArchive;

#[CoversClass(ZipModuleArchive::class)]
#[CoversClass(UnsafeArchive::class)]
final class ZipModuleArchiveTest extends TestCase
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

    public function testUnpacksTheModuleWithoutTheFolderGithubWrapsItIn(): void
    {
        $zip = ModuleZip::module($this->zip(), 'acme/news', extraFiles: ['templates/a.twig' => 'hello', 'messages/en/x.php' => '<?php']);

        $this->archive()->unpack($zip, $this->target());

        $this->assertFileExists($this->target() . '/module.json');
        $this->assertFileExists($this->target() . '/src/Module.php');
        $this->assertSame('hello', file_get_contents($this->target() . '/templates/a.twig'));
        $this->assertFileExists($this->target() . '/messages/en/x.php');
        $this->assertDirectoryDoesNotExist($this->target() . '/acme-module-1a2b3c4');
    }

    public function testKeepsThePathsOfAnArchiveWithoutAWrappingFolder(): void
    {
        $zip = ModuleZip::create($this->zip(), ['module.json' => '{}', 'src/a.php' => '<?php', 'other/b.txt' => 'b']);

        $this->archive()->unpack($zip, $this->target());

        $this->assertFileExists($this->target() . '/module.json');
        $this->assertFileExists($this->target() . '/other/b.txt');
    }

    public function testDoesNotStripAFolderWhenTheFilesDoNotAllShareIt(): void
    {
        $zip = ModuleZip::create($this->zip(), ['one/module.json' => '{}', 'two/x.txt' => 'x']);

        $this->archive()->unpack($zip, $this->target());

        $this->assertFileExists($this->target() . '/one/module.json');
        $this->assertFileExists($this->target() . '/two/x.txt');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsafePaths(): iterable
    {
        yield 'a path going up' => ['../evil.php'];
        yield 'a path going up from inside' => ['folder/../../evil.php'];
        yield 'an absolute path' => ['/etc/evil.php'];
        yield 'a windows path' => ['folder\\evil.php'];
        yield 'a drive letter' => ['C:/evil.php'];
        yield 'going up in the middle' => ['a/b/../../../evil.php'];
    }

    #[DataProvider('unsafePaths')]
    public function testRefusesAPathThatCouldLeaveTheDirectory(string $path): void
    {
        $zip = ModuleZip::create($this->zip(), ['folder/module.json' => '{}', $path => 'evil']);

        $this->assertRefused($zip, UnsafeArchive::BAD_PATH);
        $this->assertFileDoesNotExist($this->directory->path . '/evil.php');
    }

    public function testRefusesALink(): void
    {
        $zip = ModuleZip::create($this->zip(), ['folder/module.json' => '{}'], ['folder/link' => '/etc/passwd']);

        $this->assertRefused($zip, UnsafeArchive::LINK);
    }

    public function testRefusesAnArchiveWithTooManyFiles(): void
    {
        $files = [];
        for ($i = 0; $i < 11; ++$i) {
            $files['folder/f' . $i . '.txt'] = 'x';
        }

        $this->assertRefused(ModuleZip::create($this->zip(), $files), UnsafeArchive::TOO_MANY_FILES, new ZipModuleArchive(10, 1000));
    }

    public function testRefusesAnArchiveThatUnpacksToTooMuch(): void
    {
        $zip = ModuleZip::create($this->zip(), ['folder/big.txt' => str_repeat('A', 5000)]);

        $this->assertRefused($zip, UnsafeArchive::TOO_LARGE, new ZipModuleArchive(10, 1000));
    }

    public function testASmallZipThatInflatesHugeIsRefusedAndNothingIsLeft(): void
    {
        $zip = ModuleZip::create($this->zip(), ['folder/module.json' => '{}', 'folder/bomb.txt' => str_repeat('0', 2_000_000)]);
        $this->assertLessThan(10_000, filesize($zip), 'The test archive has to be small.');

        $this->assertRefused($zip, UnsafeArchive::TOO_LARGE, new ZipModuleArchive(10, 100_000));
    }

    public function testRefusesSomethingThatIsNotAZipFile(): void
    {
        file_put_contents($this->zip(), 'this is not a zip file');

        $this->assertRefused($this->zip(), UnsafeArchive::NOT_AN_ARCHIVE);
    }

    public function testRefusesAnEmptyArchive(): void
    {
        $zip = new ZipArchive();
        $zip->open($this->zip(), ZipArchive::CREATE);
        $zip->close();

        // An archive without entries is not even written; either way it holds no module.
        $this->assertRefused($this->zip(), is_file($this->zip()) ? UnsafeArchive::EMPTY : UnsafeArchive::NOT_AN_ARCHIVE);
    }

    public function testNothingIsLeftInTheDirectoryWhenTheArchiveIsRefused(): void
    {
        $zip = ModuleZip::create($this->zip(), ['folder/module.json' => '{}', 'folder/src/a.php' => '<?php', 'folder/../../evil' => 'x']);
        mkdir($this->target(), 0o777, true);

        try {
            $this->archive()->unpack($zip, $this->target());
            $this->fail('The archive had to be refused.');
        } catch (UnsafeArchive) {
            $this->assertSame([], DirectoryListing::of($this->target()));
        }
    }

    private function assertRefused(string $zip, string $reason, ?ZipModuleArchive $archive = null): void
    {
        try {
            ($archive ?? $this->archive())->unpack($zip, $this->target());
            $this->fail('The archive had to be refused.');
        } catch (UnsafeArchive $unsafeArchive) {
            $this->assertSame($reason, $unsafeArchive->reason);
        }
    }

    private function archive(): ZipModuleArchive
    {
        return new ZipModuleArchive(100, 1_000_000);
    }

    private function zip(): string
    {
        return $this->directory->path . '/release.zip';
    }

    private function target(): string
    {
        return $this->directory->path . '/unpacked';
    }
}
