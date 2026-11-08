<?php

declare(strict_types=1);

namespace Yef\Tests\Application\Publishing;

use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
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
use Yef\Domain\Page\PagePath;
use Yef\Tests\Support\FakePageRenderer;
use Yef\Tests\Support\InMemoryArtifactPublisher;
use Yef\Tests\Support\InMemoryPageRepository;
use Yef\Tests\Support\TextPages;
use YefCms\TextPages\TextContentType;

#[CoversClass(PublishPage::class)]
#[CoversClass(UnpublishPage::class)]
#[CoversClass(RebuildSite::class)]
#[CoversClass(RebuildReport::class)]
final class PublishPageTest extends TestCase
{
    private const string FIRST = '01JC0000000000000000000001';

    private const string SECOND = '01JC0000000000000000000002';

    private InMemoryPageRepository $pages;

    private InMemoryArtifactPublisher $publisher;

    private PublishPage $publishPage;

    #[Override]
    protected function setUp(): void
    {
        $this->pages = new InMemoryPageRepository();
        $this->publisher = new InMemoryArtifactPublisher();
        $this->publishPage = new PublishPage(
            $this->pages,
            TextContentType::registry(),
            new FakePageRenderer(),
            $this->publisher,
        );
    }

    public function testPublishesPublishedPageAtItsAddress(): void
    {
        $this->pages->save($this->published(self::FIRST, 'blog/hello', '<p>Hi</p>'));

        $result = $this->publishPage->handle(self::FIRST);

        $this->assertSame(PublishResult::Published, $result);
        $this->assertSame(
            ['blog/hello/index.html' => '<doc title="Title of blog/hello"><p>Hi</p></doc>'],
            $this->publisher->artifacts,
        );
    }

    public function testDraftIsWithdrawnFromItsAddress(): void
    {
        $this->pages->save($this->published(self::FIRST, 'about', 'About'));
        $this->publishPage->handle(self::FIRST);
        $this->pages->save(new TextPages()->page(self::FIRST, 'about', 'About'));

        $result = $this->publishPage->handle(self::FIRST);

        $this->assertSame(PublishResult::Withdrawn, $result);
        $this->assertSame([], $this->publisher->artifacts);
    }

    public function testPageOfUnavailableTypeIsLeftAsItIs(): void
    {
        $this->pages->save($this->published(self::FIRST, 'about', 'About'));
        $this->publishPage->handle(self::FIRST);
        $this->pages->save(new Page(
            $this->published(self::FIRST, 'about', 'About')->metadata,
            new UnavailablePayload(new ContentTypeId('gallery'), new PayloadData(['photos' => '[]'])),
        ));

        $result = $this->publishPage->handle(self::FIRST);

        $this->assertSame(PublishResult::Skipped, $result);
        $this->assertSame(['about/index.html'], array_keys($this->publisher->artifacts));
    }

    public function testUnknownPageIsReported(): void
    {
        $this->expectException(PageNotFound::class);

        $this->publishPage->handle(self::FIRST);
    }

    public function testUnpublishRemovesArtifactAtAddress(): void
    {
        $this->pages->save($this->published(self::FIRST, 'about', 'About'));
        $this->publishPage->handle(self::FIRST);

        new UnpublishPage($this->publisher)->handle(new PagePath('about'));

        $this->assertSame([], $this->publisher->artifacts);
    }

    public function testRebuildPublishesEveryPageAndCountsResults(): void
    {
        $this->pages->save($this->published(self::FIRST, '', 'Home'));
        $this->pages->save(new TextPages()->page(self::SECOND, 'draft', 'Draft'));

        $report = new RebuildSite($this->pages, $this->publishPage)->handle();

        $this->assertSame(1, $report->count(PublishResult::Published));
        $this->assertSame(1, $report->count(PublishResult::Withdrawn));
        $this->assertSame(0, $report->count(PublishResult::Skipped));
        $this->assertFalse($report->hasFailures());
        $this->assertSame(['index.html'], array_keys($this->publisher->artifacts));
    }

    private function published(string $id, string $path, string $html): Page
    {
        return new TextPages()->page($id, $path, $html)->publish(new DateTimeImmutable('2026-11-09 12:00:00 UTC'));
    }
}
