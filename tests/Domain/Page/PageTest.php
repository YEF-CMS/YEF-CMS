<?php

declare(strict_types=1);

namespace Yef\Tests\Domain\Page;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Yef\Domain\Page\Page;
use Yef\Domain\Page\PageContent;
use Yef\Domain\Page\PageId;
use Yef\Domain\Page\PagePath;
use Yef\Domain\Page\PageStatus;

#[CoversClass(Page::class)]
#[CoversClass(PageContent::class)]
final class PageTest extends TestCase
{
    private const string CREATED = '2026-11-08 10:00:00 UTC';

    private const string LATER = '2026-11-08 11:00:00 UTC';

    public function testNewPageIsDraft(): void
    {
        $page = $this->page();

        $this->assertSame(PageStatus::Draft, $page->status);
        $this->assertFalse($page->isPublished());
        $this->assertEquals($page->createdAt, $page->updatedAt);
    }

    public function testPublishAndUnpublishReturnNewPageAndTouchUpdatedAt(): void
    {
        $draft = $this->page();
        $later = new DateTimeImmutable(self::LATER);

        $published = $draft->publish($later);

        $this->assertTrue($published->isPublished());
        $this->assertEquals($later, $published->updatedAt);
        $this->assertEquals($draft->createdAt, $published->createdAt);
        $this->assertFalse($draft->isPublished(), 'the original page must stay unchanged');
        $this->assertFalse($published->unpublish($later)->isPublished());
    }

    public function testMoveToChangesOnlyThePath(): void
    {
        $page = $this->page();

        $moved = $page->moveTo(new PagePath('blog/hello'), new DateTimeImmutable(self::LATER));

        $this->assertSame('blog/hello', $moved->path->value);
        $this->assertSame($page->content, $moved->content);
        $this->assertTrue($page->id->equals($moved->id));
    }

    public function testRewriteChangesOnlyTheContent(): void
    {
        $page = $this->page();
        $content = new PageContent('New title', '<p>New</p>', 'Meta', 'Description');

        $rewritten = $page->rewrite($content, new DateTimeImmutable(self::LATER));

        $this->assertSame($content, $rewritten->content);
        $this->assertSame($page->path, $rewritten->path);
    }

    public function testContentRequiresTitle(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PageContent('   ', '<p>Body</p>');
    }

    private function page(): Page
    {
        return Page::create(
            new PageId('01ARZ3NDEKTSV4RRFFQ69G5FAV'),
            new PagePath('about'),
            new PageContent('About', '<p>Hi</p>'),
            new DateTimeImmutable(self::CREATED),
        );
    }
}
