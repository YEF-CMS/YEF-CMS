<?php

declare(strict_types=1);

namespace Yef\Tests\Storage;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Yef\Domain\Page\IntegrityReport;
use Yef\Domain\Page\PageId;
use Yef\Storage\FilePageIntegrity;
use Yef\Storage\FilePageRepository;
use Yef\Storage\PageScan;
use Yef\Storage\PageScanner;
use Yef\Storage\StorageLayout;
use Yef\Tests\Support\TemporaryDirectory;
use Yef\Tests\Support\TextPages;
use YefCms\TextPages\TextContentType;

#[CoversClass(FilePageIntegrity::class)]
#[CoversClass(IntegrityReport::class)]
#[CoversClass(PageScanner::class)]
#[CoversClass(PageScan::class)]
#[CoversClass(StorageLayout::class)]
final class FilePageIntegrityTest extends TestCase
{
    private const string FIRST_ID = '01ARZ3NDEKTSV4RRFFQ69G5001';

    private const string SECOND_ID = '01ARZ3NDEKTSV4RRFFQ69G5002';

    private TemporaryDirectory $directory;

    private StorageLayout $layout;

    private TextPages $pages;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = new TemporaryDirectory();
        $this->layout = new StorageLayout(
            $this->directory->path . '/content/pages',
            $this->directory->path . '/var/storage',
        );
        $this->pages = new TextPages();
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->directory->delete();
    }

    public function testStorageThatWasNeverUsedIsHealthy(): void
    {
        $this->assertTrue($this->integrity()->check()->isHealthy());
    }

    public function testHealthyStorageWithForeignFilesIsHealthy(): void
    {
        $this->repository()->save($this->pages->page(self::FIRST_ID, 'about', '<p>A</p>'));
        file_put_contents($this->layout->pagesDirectory . '/notes.txt', 'mine');
        mkdir($this->layout->pagesDirectory . '/drafts');

        $this->assertTrue($this->integrity()->check()->isHealthy());
    }

    public function testCountsLeftoversWithoutTouchingThem(): void
    {
        $this->repository()->save($this->pages->page(self::FIRST_ID, 'about', '<p>A</p>'));
        $this->createLeftovers(self::FIRST_ID);

        $report = $this->integrity()->check();

        $this->assertSame(5, $report->leftovers);
        $this->assertSame([], $report->damagedPages);
        $this->assertSame(5, $this->integrity()->check()->leftovers);
    }

    public function testRepairRemovesLeftoversAndKeepsThePage(): void
    {
        $page = $this->pages->page(self::FIRST_ID, 'about', '<p>A</p>');
        $this->repository()->save($page);
        $this->createLeftovers(self::FIRST_ID);

        $report = $this->integrity()->repair();

        $this->assertTrue($report->isHealthy());
        $this->assertTrue($this->integrity()->check()->isHealthy());
        $this->assertEquals($page, $this->repository()->get($page->metadata->id));
    }

    public function testRepairRestoresPageWhoseWriteDiedBetweenTwoRenames(): void
    {
        $page = $this->pages->page(self::FIRST_ID, 'about', '<p>A</p>');
        $this->repository()->save($page);
        rename(
            $this->layout->pagesDirectory . '/' . self::FIRST_ID,
            $this->layout->pagesDirectory . '/' . self::FIRST_ID . '.old-aaaaaaaaaaaa',
        );

        $report = $this->integrity()->repair();

        $this->assertTrue($report->isHealthy());
        $this->assertEquals($page, $this->repository()->get($page->metadata->id));
        $this->assertEquals([$page->metadata], $this->repository()->allMetadata());
    }

    public function testRepairDoesNotRestoreAPreviousVersionWithoutMetadata(): void
    {
        mkdir($this->layout->pagesDirectory . '/' . self::FIRST_ID . '.old-aaaaaaaaaaaa', 0o775, true);

        $report = $this->integrity()->repair();

        $this->assertTrue($report->isHealthy());
        $this->assertSame([], $this->repository()->allMetadata());
        $this->assertDirectoryDoesNotExist($this->layout->pagesDirectory . '/' . self::FIRST_ID);
    }

    public function testDamagedPageIsReportedAndLeftUntouched(): void
    {
        $healthy = $this->pages->page(self::FIRST_ID, 'about', '<p>A</p>');
        $this->repository()->save($healthy);
        $this->repository()->save($this->pages->page(self::SECOND_ID, 'contacts', '<p>B</p>'));
        file_put_contents($this->layout->pagesDirectory . '/' . self::SECOND_ID . '/meta.json', 'not json');

        $checked = $this->integrity()->check();
        $repaired = $this->integrity()->repair();

        $this->assertEquals([new PageId(self::SECOND_ID)], $checked->damagedPages);
        $this->assertEquals([new PageId(self::SECOND_ID)], $repaired->damagedPages);
        $this->assertFalse($repaired->isHealthy());
        $this->assertSame('not json', file_get_contents($this->layout->pagesDirectory . '/' . self::SECOND_ID . '/meta.json'));
        $this->assertEquals([$healthy->metadata], $this->repository()->allMetadata());
    }

    public function testRepairRebuildsALostIndex(): void
    {
        $page = $this->pages->page(self::FIRST_ID, 'about', '<p>A</p>');
        $this->repository()->save($page);
        unlink($this->layout->indexFile());

        $this->integrity()->repair();

        $this->assertFileExists($this->layout->indexFile());
        $this->assertEquals([$page->metadata], $this->repository()->allMetadata());
    }

    /**
     * Five leftovers of the kinds an interrupted write can leave next to an existing page.
     */
    private function createLeftovers(string $id): void
    {
        $pages = $this->layout->pagesDirectory;
        mkdir($pages . '/' . $id . '.new-aaaaaaaaaaaa/payload', 0o775, true);
        mkdir($pages . '/' . $id . '.old-bbbbbbbbbbbb');
        file_put_contents($pages . '/' . $id . '/meta.json.tmp-cccccccccccc', 'half');
        file_put_contents($pages . '/' . $id . '/payload/body.txt.tmp-dddddddddddd', 'half');
        file_put_contents($this->layout->stateDirectory . '/pages-index.json.tmp-eeeeeeeeeeee', 'half');
    }

    private function repository(): FilePageRepository
    {
        return new FilePageRepository($this->layout, TextContentType::registry());
    }

    private function integrity(): FilePageIntegrity
    {
        return new FilePageIntegrity($this->layout);
    }
}
