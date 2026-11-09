<?php

declare(strict_types=1);

namespace Yef\Tests\Contract;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Yef\Domain\Page\ContentVersion;
use Yef\Domain\Page\PageId;
use Yef\Domain\Page\PagePath;
use Yef\Domain\Publishing\PublishedPage;
use Yef\Domain\Publishing\PublishedPages;
use Yef\Storage\FilePublishedPages;
use Yef\Storage\StorageLayout;
use Yef\Tests\Support\TemporaryDirectory;

#[CoversClass(FilePublishedPages::class)]
final class FilePublishedPagesTest extends PublishedPagesContract
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

    public function testRecordSurvivesANewInstance(): void
    {
        $entry = $this->entry('01ARZ3NDEKTSV4RRFFQ69G5001', 'about', 'a');
        $this->createPublishedPages()->record($entry);

        $this->assertEquals([$entry], $this->createPublishedPages()->all());
    }

    public function testLostRecordReadsAsEmpty(): void
    {
        $published = $this->createPublishedPages();
        $published->record($this->entry('01ARZ3NDEKTSV4RRFFQ69G5001', 'about', 'a'));

        unlink($this->layout()->publishedFile());

        $this->assertSame([], $published->all());
    }

    public function testDamagedRecordReadsAsEmptyAndCanBeWrittenAgain(): void
    {
        $published = $this->createPublishedPages();
        $published->record($this->entry('01ARZ3NDEKTSV4RRFFQ69G5001', 'about', 'a'));
        file_put_contents($this->layout()->publishedFile(), '{"pages": [');

        $this->assertSame([], $published->all());

        $entry = $this->entry('01ARZ3NDEKTSV4RRFFQ69G5002', 'blog', 'b');
        $published->record($entry);
        $this->assertEquals([$entry], $published->all());
    }

    #[Override]
    protected function createPublishedPages(): PublishedPages
    {
        return new FilePublishedPages($this->layout());
    }

    private function layout(): StorageLayout
    {
        return new StorageLayout($this->directory->path . '/content/pages', $this->directory->path . '/var/storage');
    }

    private function entry(string $id, string $path, string $seed): PublishedPage
    {
        return new PublishedPage(new PageId($id), new PagePath($path), new ContentVersion(hash('sha256', $seed)));
    }
}
