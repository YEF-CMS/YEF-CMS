<?php

declare(strict_types=1);

namespace Yef\Tests\Application\Publishing;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Yef\Application\Publishing\PublishedHtml;
use Yef\Application\Publishing\PublishPage;
use Yef\Application\Publishing\PublishResult;
use Yef\Application\Publishing\RebuildReport;
use Yef\Application\Publishing\RebuildSite;
use Yef\Application\Publishing\UnpublishPage;
use Yef\Domain\ContentType\ContentTypeId;
use Yef\Domain\ContentType\PayloadData;
use Yef\Domain\ContentType\UnavailablePayload;
use Yef\Domain\Page\Exception\PageNotFound;
use Yef\Domain\Page\Page;
use Yef\Domain\Page\PageId;
use Yef\Tests\Support\PublishingScenario;

#[CoversClass(PublishPage::class)]
#[CoversClass(UnpublishPage::class)]
#[CoversClass(PublishedHtml::class)]
#[CoversClass(RebuildSite::class)]
#[CoversClass(RebuildReport::class)]
final class PublishPageTest extends TestCase
{
    private const string FIRST = '01JC0000000000000000000001';

    private const string SECOND = '01JC0000000000000000000002';

    private PublishingScenario $site;

    #[Override]
    protected function setUp(): void
    {
        $this->site = new PublishingScenario();
    }

    public function testPublishesPublishedPageAtItsAddress(): void
    {
        $this->site->pages->save($this->site->publishedPage(self::FIRST, 'blog/hello', '<p>Hi</p>'));

        $result = $this->site->publishPage->handle(self::FIRST);

        $this->assertSame(PublishResult::Published, $result);
        $this->assertSame(
            ['blog/hello/index.html' => '<doc title="Title of blog/hello"><p>Hi</p></doc>'],
            $this->site->publisher->artifacts,
        );
    }

    public function testRemembersWhatItPublished(): void
    {
        $page = $this->site->publishedPage(self::FIRST, 'about', 'About');
        $this->site->pages->save($page);

        $this->site->publishPage->handle(self::FIRST);

        $entry = $this->site->published->find(new PageId(self::FIRST));
        $this->assertInstanceOf(\Yef\Domain\Publishing\PublishedPage::class, $entry);
        $this->assertSame('about', $entry->path->value);
        $this->assertTrue($entry->version->equals($this->site->pages->versionOf(new PageId(self::FIRST))));
    }

    public function testDraftIsWithdrawnFromItsAddress(): void
    {
        $this->site->pages->save($this->site->publishedPage(self::FIRST, 'about', 'About'));
        $this->site->publishPage->handle(self::FIRST);
        $this->site->pages->save($this->site->draftPage(self::FIRST, 'about', 'About'));

        $result = $this->site->publishPage->handle(self::FIRST);

        $this->assertSame(PublishResult::Withdrawn, $result);
        $this->assertSame([], $this->site->publisher->artifacts);
        $this->assertSame([], $this->site->published->all());
    }

    public function testMovedPageLeavesNothingAtItsOldAddress(): void
    {
        $this->site->pages->save($this->site->publishedPage(self::FIRST, 'about', 'About'));
        $this->site->publishPage->handle(self::FIRST);
        $this->site->pages->save($this->site->publishedPage(self::FIRST, 'about-us', 'About'));

        $this->site->publishPage->handle(self::FIRST);

        $this->assertSame(['about-us/index.html'], array_keys($this->site->publisher->artifacts));
        $this->assertSame('about-us', $this->site->published->find(new PageId(self::FIRST))?->path->value);
    }

    public function testDraftThatWasMovedLeavesNothingAtItsOldAddress(): void
    {
        $this->site->pages->save($this->site->publishedPage(self::FIRST, 'about', 'About'));
        $this->site->publishPage->handle(self::FIRST);
        $this->site->pages->save($this->site->draftPage(self::FIRST, 'about-us', 'About'));

        $this->site->publishPage->handle(self::FIRST);

        $this->assertSame([], $this->site->publisher->artifacts);
    }

    public function testMovedPageDoesNotRemoveAnotherPageNowPublishedAtItsOldAddress(): void
    {
        $this->site->pages->save($this->site->publishedPage(self::FIRST, 'about', 'Old'));
        $this->site->publishPage->handle(self::FIRST);
        $this->site->pages->save($this->site->publishedPage(self::FIRST, 'about-us', 'Old'));
        $this->site->pages->save($this->site->publishedPage(self::SECOND, 'about', 'New'));
        $this->site->publishPage->handle(self::SECOND);

        $this->site->publishPage->handle(self::FIRST);

        $this->assertSame(['about/index.html', 'about-us/index.html'], array_keys($this->site->publisher->artifacts));
        $this->assertStringContainsString('New', $this->site->publisher->artifacts['about/index.html'] ?? '');
    }

    public function testPageOfUnavailableTypeIsLeftAsItIs(): void
    {
        $this->site->pages->save($this->site->publishedPage(self::FIRST, 'about', 'About'));
        $this->site->publishPage->handle(self::FIRST);
        $this->site->pages->save(new Page(
            $this->site->publishedPage(self::FIRST, 'about', 'About')->metadata,
            new UnavailablePayload(new ContentTypeId('gallery'), new PayloadData(['photos' => '[]'])),
        ));

        $result = $this->site->publishPage->handle(self::FIRST);

        $this->assertSame(PublishResult::Skipped, $result);
        $this->assertSame(['about/index.html'], array_keys($this->site->publisher->artifacts));
        $this->assertCount(1, $this->site->published->all());
    }

    public function testUnknownPageIsReported(): void
    {
        $this->expectException(PageNotFound::class);

        $this->site->publishPage->handle(self::FIRST);
    }

    public function testUnpublishRemovesHtmlAndRecordOfAPage(): void
    {
        $this->site->pages->save($this->site->publishedPage(self::FIRST, 'about', 'About'));
        $this->site->publishPage->handle(self::FIRST);

        $this->site->unpublishPage->handle(new PageId(self::FIRST));

        $this->assertSame([], $this->site->publisher->artifacts);
        $this->assertSame([], $this->site->published->all());
    }

    public function testUnpublishWorksForAPageThatIsAlreadyDeleted(): void
    {
        $this->site->pages->save($this->site->publishedPage(self::FIRST, 'about', 'About'));
        $this->site->publishPage->handle(self::FIRST);
        $this->site->pages->delete(new PageId(self::FIRST));

        $this->site->unpublishPage->handle(new PageId(self::FIRST));

        $this->assertSame([], $this->site->publisher->artifacts);
    }

    public function testUnpublishOfAPageThatWasNeverPublishedDoesNothing(): void
    {
        $this->site->publisher->artifacts = ['about/index.html' => 'kept'];

        $this->site->unpublishPage->handle(new PageId(self::FIRST));

        $this->assertSame(['about/index.html' => 'kept'], $this->site->publisher->artifacts);
    }

    public function testRebuildPublishesEveryPageAndCountsResults(): void
    {
        $this->site->pages->save($this->site->publishedPage(self::FIRST, '', 'Home'));
        $this->site->pages->save($this->site->draftPage(self::SECOND, 'draft', 'Draft'));

        $report = $this->site->rebuildSite->handle();

        $this->assertSame(1, $report->count(PublishResult::Published));
        $this->assertSame(1, $report->count(PublishResult::Withdrawn));
        $this->assertSame(0, $report->count(PublishResult::Skipped));
        $this->assertFalse($report->hasFailures());
        $this->assertSame(['index.html'], array_keys($this->site->publisher->artifacts));
    }
}
