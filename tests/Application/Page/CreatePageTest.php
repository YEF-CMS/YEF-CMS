<?php

declare(strict_types=1);

namespace Yef\Tests\Application\Page;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Yef\Application\Page\CreatePage;
use Yef\Application\Page\CreatePageCommand;
use Yef\Domain\ContentType\Exception\InvalidPayload;
use Yef\Domain\ContentType\Exception\UnknownContentType;
use Yef\Domain\Page\Exception\PathAlreadyTaken;
use Yef\Domain\Page\PageStatus;
use Yef\Tests\Support\FrozenClock;
use Yef\Tests\Support\InMemoryPageRepository;
use Yef\Tests\Support\SequentialPageIdGenerator;
use Yef\Tests\Support\Text\TextContentType;
use Yef\Tests\Support\Text\TextPayload;

#[CoversClass(CreatePage::class)]
#[CoversClass(CreatePageCommand::class)]
final class CreatePageTest extends TestCase
{
    private InMemoryPageRepository $pages;

    private CreatePage $createPage;

    #[Override]
    protected function setUp(): void
    {
        $this->pages = new InMemoryPageRepository();
        $this->createPage = new CreatePage(
            $this->pages,
            TextContentType::registry(),
            new SequentialPageIdGenerator(),
            new FrozenClock(),
        );
    }

    public function testCreatesDraftPageAndStoresIt(): void
    {
        $page = $this->createPage->handle(
            new CreatePageCommand('text', 'about', 'About', ['body' => '<p>Hi</p>'], 'Meta', 'Description'),
        );

        $this->assertSame(PageStatus::Draft, $page->metadata->status);
        $this->assertSame('text', $page->metadata->type->value);
        $this->assertSame('about', $page->metadata->details->path->value);
        $this->assertSame('About', $page->metadata->details->title);
        $this->assertSame('Meta', $page->metadata->details->metaTitle);
        $this->assertEquals(new TextPayload('<p>Hi</p>'), $page->payload);
        $this->assertSame($page, $this->pages->get($page->metadata->id));
    }

    public function testRejectsPathUsedByAnotherPage(): void
    {
        $this->createPage->handle(new CreatePageCommand('text', 'about', 'About', []));

        $this->expectException(PathAlreadyTaken::class);

        $this->createPage->handle(new CreatePageCommand('text', 'about', 'Other', []));
    }

    public function testRejectsUnknownContentType(): void
    {
        $this->expectException(UnknownContentType::class);

        $this->createPage->handle(new CreatePageCommand('gallery', 'photos', 'Photos', []));
    }

    public function testRejectsInvalidPayloadWithoutStoringAnything(): void
    {
        try {
            $this->createPage->handle(new CreatePageCommand('text', 'about', 'About', ['body' => 42]));
            $this->fail('An invalid payload must be rejected.');
        } catch (InvalidPayload) {
            $this->assertSame([], $this->pages->allMetadata());
        }
    }

    public function testRejectsInvalidPathWithoutStoringAnything(): void
    {
        try {
            $this->createPage->handle(new CreatePageCommand('text', 'Bad Path', 'Title', []));
            $this->fail('An invalid path must be rejected.');
        } catch (InvalidArgumentException) {
            $this->assertSame([], $this->pages->allMetadata());
        }
    }

    public function testRejectsEmptyTitleWithoutStoringAnything(): void
    {
        try {
            $this->createPage->handle(new CreatePageCommand('text', 'about', ' ', []));
            $this->fail('An empty title must be rejected.');
        } catch (InvalidArgumentException) {
            $this->assertSame([], $this->pages->allMetadata());
        }
    }
}
