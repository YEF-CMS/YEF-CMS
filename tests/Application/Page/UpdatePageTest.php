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
use Yef\Domain\ContentType\Exception\InvalidPayload;
use Yef\Domain\Page\Exception\PageNotFound;
use Yef\Domain\Page\Exception\PathAlreadyTaken;
use Yef\Domain\Page\Page;
use Yef\Tests\Support\FrozenClock;
use Yef\Tests\Support\InMemoryPageRepository;
use Yef\Tests\Support\SequentialPageIdGenerator;
use Yef\Tests\Support\Text\TextContentType;
use Yef\Tests\Support\Text\TextPayload;

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
        $this->updatePage = new UpdatePage($this->pages, TextContentType::registry(), $this->clock);
        $this->createPage = new CreatePage(
            $this->pages,
            TextContentType::registry(),
            new SequentialPageIdGenerator(),
            $this->clock,
        );
    }

    public function testUpdatesDetailsPayloadAndStatus(): void
    {
        $page = $this->create('about');
        $this->clock->moveTo('2026-11-09 08:00:00 UTC');

        $updated = $this->updatePage->handle(new UpdatePageCommand(
            $page->metadata->id->value,
            'company/about',
            'About us',
            ['body' => '<p>New</p>'],
            true,
            'Meta',
            'Description',
        ));

        $this->assertSame('company/about', $updated->metadata->details->path->value);
        $this->assertSame('About us', $updated->metadata->details->title);
        $this->assertSame('Description', $updated->metadata->details->metaDescription);
        $this->assertEquals(new TextPayload('<p>New</p>'), $updated->payload);
        $this->assertTrue($updated->metadata->isPublished());
        $this->assertEquals($this->clock->now(), $updated->metadata->updatedAt);
        $this->assertEquals($page->metadata->createdAt, $updated->metadata->createdAt);
        $this->assertSame('text', $updated->metadata->type->value);
        $this->assertSame($updated, $this->pages->get($page->metadata->id));
    }

    public function testPublishedPageCanBeReturnedToDraft(): void
    {
        $page = $this->create('about');
        $id = $page->metadata->id->value;
        $this->updatePage->handle(new UpdatePageCommand($id, 'about', 'About', [], true));

        $draft = $this->updatePage->handle(new UpdatePageCommand($id, 'about', 'About', [], false));

        $this->assertFalse($draft->metadata->isPublished());
    }

    public function testPageMayKeepItsOwnPath(): void
    {
        $page = $this->create('about');

        $updated = $this->updatePage->handle(
            new UpdatePageCommand($page->metadata->id->value, 'about', 'Renamed', [], false),
        );

        $this->assertSame('Renamed', $updated->metadata->details->title);
    }

    public function testRejectsPathUsedByAnotherPage(): void
    {
        $this->create('contacts');
        $about = $this->create('about');

        $this->expectException(PathAlreadyTaken::class);

        $this->updatePage->handle(new UpdatePageCommand($about->metadata->id->value, 'contacts', 'About', [], false));
    }

    public function testInvalidPayloadLeavesStoredPageUntouched(): void
    {
        $page = $this->create('about');

        try {
            $this->updatePage->handle(
                new UpdatePageCommand($page->metadata->id->value, 'about', 'About', ['body' => 42], true),
            );
            $this->fail('An invalid payload must be rejected.');
        } catch (InvalidPayload) {
            $this->assertSame($page, $this->pages->get($page->metadata->id));
        }
    }

    public function testRejectsUnknownPage(): void
    {
        $this->expectException(PageNotFound::class);

        $this->updatePage->handle(new UpdatePageCommand('01ARZ3NDEKTSV4RRFFQ69G5FAV', 'about', 'About', [], false));
    }

    private function create(string $path): Page
    {
        return $this->createPage->handle(new CreatePageCommand('text', $path, ucfirst($path), ['body' => '<p>Hi</p>']));
    }
}
