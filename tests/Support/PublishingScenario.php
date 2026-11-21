<?php

declare(strict_types=1);

namespace Yef\Tests\Support;

use DateTimeImmutable;
use Yef\Application\Publishing\ApplyChange;
use Yef\Application\Publishing\ApplyChanges;
use Yef\Application\Publishing\ApplyPageChange;
use Yef\Application\Publishing\PlanChanges;
use Yef\Application\Publishing\PublishedHtml;
use Yef\Application\Publishing\PublishPage;
use Yef\Application\Publishing\RebuildSite;
use Yef\Application\Publishing\UnpublishPage;
use Yef\Domain\Page\Page;
use YefCms\TextPages\TextContentType;

/**
 * The publishing services wired to in-memory ports, as the application tests need them.
 */
final readonly class PublishingScenario
{
    public InMemoryPageRepository $pages;

    public InMemoryPublishedPages $published;

    public InMemoryArtifactPublisher $publisher;

    public PublishPage $publishPage;

    public UnpublishPage $unpublishPage;

    public PlanChanges $planChanges;

    public ApplyChanges $applyChanges;

    public ApplyPageChange $applyPageChange;

    public RebuildSite $rebuildSite;

    public function __construct()
    {
        $this->pages = new InMemoryPageRepository();
        $this->published = new InMemoryPublishedPages();
        $this->publisher = new InMemoryArtifactPublisher();
        $html = new PublishedHtml($this->publisher, $this->published);
        $this->publishPage = new PublishPage(
            $this->pages,
            $this->pages,
            $this->published,
            $html,
            TextContentType::registry(),
            new FakePageRenderer(),
            $this->publisher,
        );
        $this->unpublishPage = new UnpublishPage($this->published, $html);
        $this->planChanges = new PlanChanges($this->pages, $this->pages, $this->published, TextContentType::registry());
        $applyChange = new ApplyChange($this->publishPage, $this->unpublishPage);
        $this->applyChanges = new ApplyChanges($this->planChanges, $applyChange);
        $this->applyPageChange = new ApplyPageChange($this->planChanges, $applyChange);
        $this->rebuildSite = new RebuildSite($this->pages, $this->publishPage);
    }

    public function publishedPage(string $id, string $path, string $html): Page
    {
        return new TextPages()->page($id, $path, $html)->publish(new DateTimeImmutable('2026-11-09 12:00:00 UTC'));
    }

    public function draftPage(string $id, string $path, string $html): Page
    {
        return new TextPages()->page($id, $path, $html);
    }
}
