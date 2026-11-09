<?php

declare(strict_types=1);

namespace Yef\Tests\Contract;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yef\Domain\ContentType\ContentTypeId;
use Yef\Domain\ContentType\PayloadData;
use Yef\Domain\ContentType\UnavailablePayload;
use Yef\Domain\Page\Exception\PageNotFound;
use Yef\Domain\Page\Exception\PathAlreadyTaken;
use Yef\Domain\Page\Page;
use Yef\Domain\Page\PageDetails;
use Yef\Domain\Page\PageId;
use Yef\Domain\Page\PageMetadata;
use Yef\Domain\Page\PagePath;
use Yef\Domain\Page\PageRepository;
use Yef\Domain\Page\PageStatus;
use Yef\Domain\Page\PageVersions;
use YefCms\TextPages\TextContentType;
use YefCms\TextPages\TextPayload;

/**
 * Behavior every PageRepository implementation has to provide, whatever it stores pages in.
 */
abstract class PageRepositoryContract extends TestCase
{
    private const string FIRST_ID = '01ARZ3NDEKTSV4RRFFQ69G5001';

    private const string SECOND_ID = '01ARZ3NDEKTSV4RRFFQ69G5002';

    public function testSavedPageCanBeReadBack(): void
    {
        $repository = $this->createRepository();
        $page = $this->textPage(self::FIRST_ID, 'about', '<p>Привет, "мир"</p>' . "\n");

        $repository->save($page);

        $this->assertEquals($page, $repository->get($page->metadata->id));
    }

    public function testMetadataKeepsEveryField(): void
    {
        $repository = $this->createRepository();
        $details = new PageDetails(new PagePath('blog/hello'), 'Hello', 'Meta title', 'Meta description');
        $metadata = new PageMetadata(
            new PageId(self::FIRST_ID),
            new ContentTypeId(TextContentType::ID),
            $details,
            PageStatus::Published,
            new DateTimeImmutable('2026-01-02 03:04:05.678901 +03:00'),
            new DateTimeImmutable('2026-05-06 07:08:09.123456 UTC'),
        );

        $repository->save(new Page($metadata, new TextPayload('<p>Hi</p>')));

        $this->assertEquals($metadata, $repository->get($metadata->id)->metadata);
    }

    public function testSavingAgainReplacesThePage(): void
    {
        $repository = $this->createRepository();
        $repository->save($this->textPage(self::FIRST_ID, 'about', '<p>Old</p>'));

        $updated = $this->textPage(self::FIRST_ID, 'about-us', '<p>New</p>');

        $repository->save($updated);

        $this->assertEquals($updated, $repository->get($updated->metadata->id));
        $this->assertCount(1, $repository->allMetadata());
    }

    public function testMissingPageIsReported(): void
    {
        $this->expectException(PageNotFound::class);

        $this->createRepository()->get(new PageId(self::FIRST_ID));
    }

    public function testFindsMetadataByPath(): void
    {
        $repository = $this->createRepository();
        $page = $this->textPage(self::FIRST_ID, 'blog/hello', '<p>Hi</p>');
        $repository->save($page);

        $found = $repository->findMetadataByPath(new PagePath('blog/hello'));

        $this->assertEquals($page->metadata, $found);
        $this->assertNull($repository->findMetadataByPath(new PagePath('blog')));
    }

    public function testHomePageIsFoundByEmptyPath(): void
    {
        $repository = $this->createRepository();
        $page = $this->textPage(self::FIRST_ID, '', '<p>Home</p>');
        $repository->save($page);

        $this->assertEquals($page->metadata, $repository->findMetadataByPath(PagePath::home()));
    }

    public function testListsMetadataOfAllPages(): void
    {
        $repository = $this->createRepository();
        $first = $this->textPage(self::FIRST_ID, 'about', '<p>A</p>');
        $second = $this->textPage(self::SECOND_ID, 'contacts', '<p>B</p>');
        $repository->save($first);
        $repository->save($second);

        $listed = $repository->allMetadata();

        $this->assertCount(2, $listed);
        $this->assertContainsEquals($first->metadata, $listed);
        $this->assertContainsEquals($second->metadata, $listed);
    }

    public function testEmptyRepositoryListsNothing(): void
    {
        $this->assertSame([], $this->createRepository()->allMetadata());
    }

    public function testDeletedPageIsGone(): void
    {
        $repository = $this->createRepository();
        $page = $this->textPage(self::FIRST_ID, 'about', '<p>A</p>');
        $repository->save($page);

        $repository->delete($page->metadata->id);

        $this->assertSame([], $repository->allMetadata());
        $this->assertNull($repository->findMetadataByPath(new PagePath('about')));
        $this->expectException(PageNotFound::class);
        $repository->get($page->metadata->id);
    }

    public function testDeletingMissingPageIsReported(): void
    {
        $this->expectException(PageNotFound::class);

        $this->createRepository()->delete(new PageId(self::FIRST_ID));
    }

    public function testPathCannotBeUsedByTwoPages(): void
    {
        $repository = $this->createRepository();
        $repository->save($this->textPage(self::FIRST_ID, 'about', '<p>A</p>'));

        $this->expectException(PathAlreadyTaken::class);

        $repository->save($this->textPage(self::SECOND_ID, 'about', '<p>B</p>'));
    }

    public function testPageMayKeepItsOwnPath(): void
    {
        $repository = $this->createRepository();
        $repository->save($this->textPage(self::FIRST_ID, 'about', '<p>A</p>'));

        $repository->save($this->textPage(self::FIRST_ID, 'about', '<p>B</p>'));

        $this->assertCount(1, $repository->allMetadata());
    }

    public function testFreedPathCanBeReused(): void
    {
        $repository = $this->createRepository();
        $repository->save($this->textPage(self::FIRST_ID, 'about', '<p>A</p>'));
        $repository->save($this->textPage(self::FIRST_ID, 'about-us', '<p>A</p>'));

        $repository->save($this->textPage(self::SECOND_ID, 'about', '<p>B</p>'));

        $this->assertCount(2, $repository->allMetadata());
    }

    public function testPageOfUnavailableTypeSurvivesRoundTrip(): void
    {
        $repository = $this->createRepository();
        $type = new ContentTypeId('gallery');
        $page = new Page(
            PageMetadata::draft(
                new PageId(self::FIRST_ID),
                $type,
                new PageDetails(new PagePath('photos'), 'Photos'),
                new DateTimeImmutable('2026-11-08 12:00:00 UTC'),
            ),
            new UnavailablePayload($type, new PayloadData(['photos' => '[1,2]', 'layout' => 'grid'])),
        );
        $repository->save($page);

        $restored = $repository->get($page->metadata->id);

        $this->assertInstanceOf(UnavailablePayload::class, $restored->payload);
        $this->assertSame(['photos' => '[1,2]', 'layout' => 'grid'], $restored->payload->data->parts());
    }

    public function testSavingTheSamePageAgainKeepsItsVersion(): void
    {
        $repository = $this->createRepository();
        $page = $this->textPage(self::FIRST_ID, 'about', '<p>A</p>');
        $repository->save($page);
        $before = $repository->versionOf($page->metadata->id);

        $repository->save(new Page($page->metadata->revised($page->metadata->details, new DateTimeImmutable('2027-01-01 00:00:00 UTC')), $page->payload));

        $this->assertTrue($before->equals($repository->versionOf($page->metadata->id)));
    }

    /**
     * @return iterable<string, array{Page}>
     */
    public static function changedPages(): iterable
    {
        $now = new DateTimeImmutable('2027-01-01 00:00:00 UTC');
        $original = self::pageOf(self::FIRST_ID, 'about', '<p>A</p>');

        yield 'text' => [self::pageOf(self::FIRST_ID, 'about', '<p>B</p>')];
        yield 'title' => [$original->edit(new PageDetails(new PagePath('about'), 'Other title'), $original->payload, $now)];
        yield 'address' => [self::pageOf(self::FIRST_ID, 'about-us', '<p>A</p>')];
        yield 'meta description' => [$original->edit(new PageDetails(new PagePath('about'), 'Title of about', '', 'New'), $original->payload, $now)];
        yield 'status' => [$original->publish($now)];
    }

    #[DataProvider('changedPages')]
    public function testEditorChangesChangeTheVersion(Page $changed): void
    {
        $repository = $this->createRepository();
        $repository->save(self::pageOf(self::FIRST_ID, 'about', '<p>A</p>'));

        $before = $repository->versionOf(new PageId(self::FIRST_ID));

        $repository->save($changed);

        $this->assertFalse($before->equals($repository->versionOf(new PageId(self::FIRST_ID))));
    }

    public function testListsVersionOfEveryPage(): void
    {
        $repository = $this->createRepository();
        $repository->save($this->textPage(self::FIRST_ID, 'about', '<p>A</p>'));
        $repository->save($this->textPage(self::SECOND_ID, 'contacts', '<p>B</p>'));

        $versions = $repository->all();

        $this->assertSame([self::FIRST_ID, self::SECOND_ID], array_keys($versions));
        $first = $repository->versionOf(new PageId(self::FIRST_ID));
        $second = $repository->versionOf(new PageId(self::SECOND_ID));
        $this->assertEquals([self::FIRST_ID => $first, self::SECOND_ID => $second], $versions);
        $this->assertFalse($first->equals($second));
    }

    public function testVersionOfMissingPageIsReported(): void
    {
        $this->expectException(PageNotFound::class);

        $this->createRepository()->versionOf(new PageId(self::FIRST_ID));
    }

    public function testDeletedPageHasNoVersion(): void
    {
        $repository = $this->createRepository();
        $repository->save($this->textPage(self::FIRST_ID, 'about', '<p>A</p>'));
        $repository->delete(new PageId(self::FIRST_ID));

        $this->assertSame([], $repository->all());
    }

    abstract protected function createRepository(): PageRepository&PageVersions;

    protected function textPage(string $id, string $path, string $html): Page
    {
        return self::pageOf($id, $path, $html);
    }

    private static function pageOf(string $id, string $path, string $html): Page
    {
        return new Page(
            PageMetadata::draft(
                new PageId($id),
                new ContentTypeId(TextContentType::ID),
                new PageDetails(new PagePath($path), 'Title of ' . ($path === '' ? 'home' : $path)),
                new DateTimeImmutable('2026-11-08 12:00:00 UTC'),
            ),
            new TextPayload($html),
        );
    }
}
