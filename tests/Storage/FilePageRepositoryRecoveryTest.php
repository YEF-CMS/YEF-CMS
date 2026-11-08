<?php

declare(strict_types=1);

namespace Yef\Tests\Storage;

use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Yef\Domain\ContentType\ContentTypeId;
use Yef\Domain\ContentType\ContentTypeRegistry;
use Yef\Domain\ContentType\UnavailablePayload;
use Yef\Domain\Page\Exception\PageDataCorrupted;
use Yef\Domain\Page\Page;
use Yef\Domain\Page\PageDetails;
use Yef\Domain\Page\PageId;
use Yef\Domain\Page\PageMetadata;
use Yef\Domain\Page\PagePath;
use Yef\Storage\FilePageRepository;
use Yef\Storage\StorageLayout;
use Yef\Tests\Support\TemporaryDirectory;
use Yef\Tests\Support\Text\TextContentType;
use Yef\Tests\Support\Text\TextPayload;

/**
 * What only the file storage promises: it survives interrupted writes, lost indexes and parallel requests.
 * The behavior shared with other storages is checked in tests/Contract.
 */
#[CoversClass(FilePageRepository::class)]
final class FilePageRepositoryRecoveryTest extends TestCase
{
    private const string FIRST_ID = '01ARZ3NDEKTSV4RRFFQ69G5001';

    private const string SECOND_ID = '01ARZ3NDEKTSV4RRFFQ69G5002';

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

    public function testLeftoversOfInterruptedWritesDoNotDisturbReadsOrWrites(): void
    {
        $repository = $this->repository();
        $page = $this->textPage(self::FIRST_ID, 'about', '<p>Old</p>');
        $repository->save($page);
        $pages = $this->directory->path . '/content/pages';
        mkdir($pages . '/' . self::FIRST_ID . '.new-abc/payload', 0o775, true);
        file_put_contents($pages . '/' . self::FIRST_ID . '.new-abc/meta.json', '{');
        mkdir($pages . '/' . self::FIRST_ID . '.old-abc');
        file_put_contents($pages . '/' . self::FIRST_ID . '/meta.json.tmp-abc', 'half');

        $this->assertEquals($page, $repository->get($page->metadata->id));
        $this->assertCount(1, $repository->allMetadata());
        $updated = $this->textPage(self::FIRST_ID, 'about', '<p>New</p>');
        $repository->save($updated);
        $this->assertEquals($updated, $repository->get($updated->metadata->id));
    }

    public function testIndexIsRebuiltWhenItIsLost(): void
    {
        $first = $this->textPage(self::FIRST_ID, 'about', '<p>A</p>');
        $second = $this->textPage(self::SECOND_ID, 'blog/post', '<p>B</p>');
        $this->repository()->save($first);
        $this->repository()->save($second);
        unlink($this->indexFile());

        $repository = $this->repository();

        $this->assertEquals([$first->metadata, $second->metadata], $repository->allMetadata());
        $this->assertEquals($second->metadata, $repository->findMetadataByPath(new PagePath('blog/post')));
        $this->assertFileExists($this->indexFile());
    }

    public function testIndexIsRebuiltWhenItIsDamaged(): void
    {
        $page = $this->textPage(self::FIRST_ID, 'about', '<p>A</p>');
        $this->repository()->save($page);
        file_put_contents($this->indexFile(), '{"pages": [');

        $this->assertEquals([$page->metadata], $this->repository()->allMetadata());
    }

    public function testIndexIsRebuiltBeforeWritingWhenItIsLost(): void
    {
        $this->repository()->save($this->textPage(self::FIRST_ID, 'about', '<p>A</p>'));
        unlink($this->indexFile());

        $this->repository()->save($this->textPage(self::SECOND_ID, 'contacts', '<p>B</p>'));

        $this->assertCount(2, $this->repository()->allMetadata());
    }

    public function testDamagedPageIsReportedAndDoesNotHideOthers(): void
    {
        $healthy = $this->textPage(self::FIRST_ID, 'about', '<p>A</p>');
        $broken = $this->textPage(self::SECOND_ID, 'contacts', '<p>B</p>');
        $this->repository()->save($healthy);
        $this->repository()->save($broken);
        file_put_contents($this->pageDirectory(self::SECOND_ID) . '/meta.json', 'not json');
        unlink($this->indexFile());

        $repository = $this->repository();

        $this->assertEquals([$healthy->metadata], $repository->allMetadata());
        $this->expectException(PageDataCorrupted::class);
        $repository->get($broken->metadata->id);
    }

    public function testPageWithMissingPayloadPartIsReportedAsCorrupted(): void
    {
        $page = $this->textPage(self::FIRST_ID, 'about', '<p>A</p>');
        $this->repository()->save($page);
        unlink($this->pageDirectory(self::FIRST_ID) . '/payload/body.txt');

        $this->expectException(PageDataCorrupted::class);

        $this->repository()->get($page->metadata->id);
    }

    public function testPageOfUnavailableTypeIsWrittenBackUnchanged(): void
    {
        $page = $this->textPage(self::FIRST_ID, 'about', "<p>Привет</p>\n");
        $this->repository()->save($page);
        $before = file_get_contents($this->pageDirectory(self::FIRST_ID) . '/payload/body.txt');
        $withoutModule = $this->repository(new ContentTypeRegistry([]));

        $unavailable = $withoutModule->get($page->metadata->id);
        $withoutModule->save($unavailable);

        $this->assertInstanceOf(UnavailablePayload::class, $unavailable->payload);
        $this->assertSame($before, file_get_contents($this->pageDirectory(self::FIRST_ID) . '/payload/body.txt'));
        $this->assertEquals($page, $this->repository()->get($page->metadata->id));
    }

    public function testParallelWritersLoseNothing(): void
    {
        $workers = 4;
        $pagesPerWorker = 15;
        $processes = [];
        for ($worker = 1; $worker <= $workers; ++$worker) {
            $processes[] = $this->startWorker($worker, $pagesPerWorker);
        }

        foreach ($processes as [$process, $errors]) {
            $messages = stream_get_contents($errors);
            $this->assertSame(0, proc_close($process), (string) $messages);
        }

        $repository = $this->repository();
        $this->assertCount($workers * $pagesPerWorker, $repository->allMetadata());
        $listedBeforeRebuild = $repository->allMetadata();
        unlink($this->indexFile());
        $listedAfterRebuild = $this->repository()->allMetadata();
        $this->assertEqualsCanonicalizing($listedBeforeRebuild, $listedAfterRebuild);
        foreach ($listedAfterRebuild as $metadata) {
            $this->assertEquals($metadata, $repository->get($metadata->id)->metadata);
        }
    }

    /**
     * @return array{resource, resource}
     */
    private function startWorker(int $worker, int $pageCount): array
    {
        $process = proc_open(
            [
                PHP_BINARY,
                __DIR__ . '/../Support/save-pages-worker.php',
                $this->directory->path . '/content/pages',
                $this->directory->path . '/var/storage',
                (string) $worker,
                (string) $pageCount,
            ],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        $errors = $pipes[2] ?? null;
        $this->assertIsResource($process);
        $this->assertIsResource($errors);

        return [$process, $errors];
    }

    private function repository(?ContentTypeRegistry $types = null): FilePageRepository
    {
        return new FilePageRepository(
            new StorageLayout($this->directory->path . '/content/pages', $this->directory->path . '/var/storage'),
            $types ?? TextContentType::registry(),
        );
    }

    private function indexFile(): string
    {
        return $this->directory->path . '/var/storage/pages-index.json';
    }

    private function pageDirectory(string $id): string
    {
        return $this->directory->path . '/content/pages/' . $id;
    }

    private function textPage(string $id, string $path, string $html): Page
    {
        return new Page(
            PageMetadata::draft(
                new PageId($id),
                new ContentTypeId(TextContentType::ID),
                new PageDetails(new PagePath($path), 'Title of ' . $path),
                new DateTimeImmutable('2026-11-08 12:00:00 UTC'),
            ),
            new TextPayload($html),
        );
    }
}
