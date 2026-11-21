<?php

declare(strict_types=1);

namespace Yef\Tests\Storage;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Yef\Domain\Module\Install\Exception\ModuleFilesFailed;
use Yef\Domain\Module\Install\Exception\StagingNotFound;
use Yef\Domain\Module\Install\GitHubRepository;
use Yef\Domain\Module\Install\ModuleRelease;
use Yef\Domain\Module\Install\ReleaseVersion;
use Yef\Domain\Module\Install\StagedOffer;
use Yef\Domain\Module\ModuleName;
use Yef\Storage\FileModuleFiles;
use Yef\Tests\Support\DirectoryListing;
use Yef\Tests\Support\TemporaryDirectory;

#[CoversClass(FileModuleFiles::class)]
#[CoversClass(StagedOffer::class)]
#[CoversClass(ModuleFilesFailed::class)]
#[CoversClass(StagingNotFound::class)]
final class FileModuleFilesTest extends TestCase
{
    private TemporaryDirectory $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = new TemporaryDirectory();
        mkdir($this->modules(), 0o777, true);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->directory->delete();
    }

    public function testNewStagingHasAPlaceForTheArchiveAndAnEmptyModule(): void
    {
        $area = $this->files()->newStaging();

        $this->assertDirectoryExists($area->directory);
        $this->assertSame([], DirectoryListing::of($area->directory));
        $this->assertDirectoryExists(dirname($area->archiveFile));
        $this->assertSame(32, strlen($area->token));
    }

    public function testStagingIsFoundAgainByItsToken(): void
    {
        $area = $this->files()->newStaging();

        $this->assertEquals($area, $this->files()->staging($area->token));
    }

    #[\PHPUnit\Framework\Attributes\DoesNotPerformAssertions]
    public function testRefusesTokensThatAreNotOnes(): void
    {
        foreach (['', '../x', str_repeat('a', 31), str_repeat('G', 32), str_repeat('a', 33), '..' . str_repeat('a', 30)] as $token) {
            try {
                $this->files()->staging($token);
                $this->fail('"' . $token . '" had to be refused.');
            } catch (StagingNotFound) {
            }
        }
    }

    public function testUnknownTokenIsNotFound(): void
    {
        $this->expectException(StagingNotFound::class);

        $this->files()->staging(str_repeat('a', 32));
    }

    public function testStagingThatHasWaitedTooLongIsGone(): void
    {
        $area = $this->files()->newStaging();
        touch(dirname($area->archiveFile), time() - 7200);

        try {
            $this->files()->staging($area->token);
            $this->fail('The area had expired.');
        } catch (StagingNotFound) {
            $this->assertDirectoryDoesNotExist(dirname($area->archiveFile));
        }
    }

    public function testMakingANewStagingThrowsAwayTheOldOnes(): void
    {
        $old = $this->files()->newStaging();
        touch(dirname($old->archiveFile), time() - 7200);

        $this->files()->newStaging();

        $this->assertDirectoryDoesNotExist(dirname($old->archiveFile));
    }

    public function testRemembersWhatWaits(): void
    {
        $area = $this->files()->newStaging();
        $offer = $this->offer('1.2.0', null);

        $this->files()->remember($area, $offer);

        $this->assertEquals($offer, $this->files()->offerOf($area));
    }

    public function testRemembersWhichModuleAnOfferReplaces(): void
    {
        $area = $this->files()->newStaging();
        $offer = $this->offer('1.2.0', new ModuleName('acme/news'));

        $this->files()->remember($area, $offer);

        $this->assertTrue($this->files()->offerOf($area)->isUpdate());
        $this->assertSame('acme/news', $this->files()->offerOf($area)->replaces?->value);
    }

    public function testNothingRememberedIsNotFound(): void
    {
        $area = $this->files()->newStaging();

        $this->expectException(StagingNotFound::class);

        $this->files()->offerOf($area);
    }

    public function testDamagedMemoryIsNotFound(): void
    {
        $area = $this->files()->newStaging();
        file_put_contents(dirname($area->archiveFile) . '/offer.json', '{"repository": "../../etc"}');

        $this->expectException(StagingNotFound::class);

        $this->files()->offerOf($area);
    }

    public function testDiscardingRemovesEverything(): void
    {
        $area = $this->files()->newStaging();
        file_put_contents($area->archiveFile, 'zip');

        $this->files()->discard($area);
        $this->files()->discard($area);

        $this->assertDirectoryDoesNotExist(dirname($area->archiveFile));
    }

    public function testInstallPutsTheModuleInItsPlaceAndThrowsTheAreaAway(): void
    {
        $area = $this->stagedModule('<?php // one');

        $this->files()->install($area, new ModuleName('acme/news'));

        $this->assertSame('<?php // one', file_get_contents($this->modules() . '/acme__news/src/Module.php'));
        $this->assertDirectoryDoesNotExist(dirname($area->archiveFile));
        $this->assertTrue($this->files()->isInstalled(new ModuleName('acme/news')));
    }

    public function testInstallRefusesToTakeThePlaceOfAnotherModule(): void
    {
        mkdir($this->modules() . '/acme__news');
        file_put_contents($this->modules() . '/acme__news/mine.txt', 'mine');
        $area = $this->stagedModule('<?php');

        try {
            $this->files()->install($area, new ModuleName('acme/news'));
            $this->fail('Installing over a module had to be refused.');
        } catch (ModuleFilesFailed) {
            $this->assertFileExists($this->modules() . '/acme__news/mine.txt');
        }
    }

    public function testReplaceSwapsTheModuleAndLeavesNoOldCopy(): void
    {
        mkdir($this->modules() . '/acme__news/src', 0o777, true);
        file_put_contents($this->modules() . '/acme__news/src/Module.php', '<?php // old');
        file_put_contents($this->modules() . '/acme__news/old-only.txt', 'gone');
        $area = $this->stagedModule('<?php // new');

        $this->files()->replace($area, new ModuleName('acme/news'));

        $this->assertSame('<?php // new', file_get_contents($this->modules() . '/acme__news/src/Module.php'));
        $this->assertFileDoesNotExist($this->modules() . '/acme__news/old-only.txt');
        $this->assertSame(['acme__news'], DirectoryListing::of($this->modules()));
    }

    public function testReplaceKeepsTheOldModuleWhenTheNewOneCannotBeMoved(): void
    {
        mkdir($this->modules() . '/acme__news/src', 0o777, true);
        file_put_contents($this->modules() . '/acme__news/src/Module.php', '<?php // old');
        $area = $this->stagedModule('<?php // new');
        // The unpacked module disappears before the swap, so the second move fails.
        new \Yef\Storage\Files()->remove($area->directory);

        try {
            $this->files()->replace($area, new ModuleName('acme/news'));
            $this->fail('The replacement had to fail.');
        } catch (ModuleFilesFailed) {
            $this->assertSame('<?php // old', file_get_contents($this->modules() . '/acme__news/src/Module.php'));
            $this->assertSame(['acme__news'], DirectoryListing::of($this->modules()));
        }
    }

    public function testReplaceNeedsTheModuleToBeThere(): void
    {
        $area = $this->stagedModule('<?php');

        $this->expectException(ModuleFilesFailed::class);

        $this->files()->replace($area, new ModuleName('acme/news'));
    }

    public function testRemoveDeletesTheModule(): void
    {
        mkdir($this->modules() . '/acme__news/src', 0o777, true);
        file_put_contents($this->modules() . '/acme__news/src/Module.php', '<?php');

        $this->files()->remove(new ModuleName('acme/news'));

        $this->assertDirectoryDoesNotExist($this->modules() . '/acme__news');
        $this->assertFalse($this->files()->isInstalled(new ModuleName('acme/news')));
    }

    public function testRemovingAModuleThatIsNotThereChangesNothing(): void
    {
        $this->files()->remove(new ModuleName('acme/news'));

        $this->assertDirectoryExists($this->modules());
    }

    public function testModulesCanBeChangedOnlyInAFolderThatIsThere(): void
    {
        $this->assertTrue($this->files()->canChange());
        $this->assertFalse(new FileModuleFiles($this->directory->path . '/nowhere', $this->staging(), 3600)->canChange());
    }

    private function stagedModule(string $moduleSource): \Yef\Domain\Module\Install\StagingArea
    {
        $area = $this->files()->newStaging();
        mkdir($area->directory . '/src', 0o777, true);
        file_put_contents($area->directory . '/src/Module.php', $moduleSource);
        $this->files()->remember($area, $this->offer('1.0.0', null));

        return $area;
    }

    private function offer(string $version, ?ModuleName $replaces): StagedOffer
    {
        return new StagedOffer(
            new ModuleRelease(GitHubRepository::fromAddress('https://github.com/acme/news'), new ReleaseVersion($version), 'https://api.github.com/x'),
            hash('sha256', $version),
            $replaces,
        );
    }

    private function files(): FileModuleFiles
    {
        return new FileModuleFiles($this->modules(), $this->staging(), 3600);
    }

    private function modules(): string
    {
        return $this->directory->path . '/modules';
    }

    private function staging(): string
    {
        return $this->directory->path . '/var/incoming';
    }
}
