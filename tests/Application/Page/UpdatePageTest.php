<?php

declare(strict_types=1);

namespace Yef\Tests\Application\Page;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Yef\Application\Page\CreatePage;
use Yef\Application\Page\CreatePageCommand;
use Yef\Application\Page\UpdatePage;
use Yef\Application\Page\UpdatePageCommand;
use Yef\Domain\Page\Exception\PageNotFound;
use Yef\Domain\Page\Exception\PathAlreadyTaken;
use Yef\Domain\Page\Page;
use Yef\Tests\Support\FrozenClock;
use Yef\Tests\Support\InMemoryPageRepository;
use Yef\Tests\Support\SequentialPageIdGenerator;

#[CoversClass(UpdatePage::class)]
#[CoversClass(UpdatePageCommand::class)]
final class UpdatePageTest extends TestCase
{
    private InMemoryPageRepository $pages;

    private FrozenClock $clock;

    private UpdatePage $updatePage;

    private CreatePage $createPage;

    #[Override]
    protected function setUp(): void
    {
        $this->pages = new InMemoryPageRepository();
        $this->clock = new FrozenClock();
        $this->updatePage = new UpdatePage($this->pages, $this->clock);
        $this->createPage = new CreatePage($this->pages, new SequentialPageIdGenerator(), $this->clock);
    }

    public function testUpdatesContentPathAndStatus(): void
    {
        $page = $this->create('about');
        $this->clock->moveTo('2026-11-09 08:00:00 UTC');

        $updated = ($this->updatePage)(
            new UpdatePageCommand($page->id->value, 'company/about', 'About us', '<p>New</p>', true, 'Meta', 'Description'),
        );

        $this->assertSame('company/about', $updated->path->value);
        $this->assertSame('About us', $updated->content->title);
        $this->assertTrue($updated->isPublished());
        $this->assertEquals($this->clock->now(), $updated->updatedAt);
        $this->assertEquals($page->createdAt, $updated->createdAt);
        $this->assertSame($updated, $this->pages->get($page->id));
    }

    public function testPublishedPageCanBeReturnedToDraft(): void
    {
        $page = $this->create('about');
        ($this->updatePage)(new UpdatePageCommand($page->id->value, 'about', 'About', '', true));

        $draft = ($this->updatePage)(new UpdatePageCommand($page->id->value, 'about', 'About', '', false));

        $this->assertFalse($draft->isPublished());
    }

    public function testPageMayKeepItsOwnPath(): void
    {
        $page = $this->create('about');

        $updated = ($this->updatePage)(new UpdatePageCommand($page->id->value, 'about', 'Renamed', '', false));

        $this->assertSame('Renamed', $updated->content->title);
    }

    public function testRejectsPathUsedByAnotherPage(): void
    {
        $this->create('contacts');
        $about = $this->create('about');

        $this->expectException(PathAlreadyTaken::class);

        ($this->updatePage)(new UpdatePageCommand($about->id->value, 'contacts', 'About', '', false));
    }

    public function testRejectsUnknownPage(): void
    {
        $this->expectException(PageNotFound::class);

        ($this->updatePage)(new UpdatePageCommand('01ARZ3NDEKTSV4RRFFQ69G5FAV', 'about', 'About', '', false));
    }

    private function create(string $path): Page
    {
        return ($this->createPage)(new CreatePageCommand($path, ucfirst($path), ''));
    }
}
