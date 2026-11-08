<?php

declare(strict_types=1);

namespace Yef\Tests\Application\Page;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Yef\Application\Page\CreatePage;
use Yef\Application\Page\CreatePageCommand;
use Yef\Application\Page\DeletePage;
use Yef\Application\Page\ListPages;
use Yef\Domain\Page\Exception\PageNotFound;
use Yef\Domain\Page\PageMetadata;
use Yef\Tests\Support\FrozenClock;
use Yef\Tests\Support\InMemoryPageRepository;
use Yef\Tests\Support\SequentialPageIdGenerator;
use Yef\Tests\Support\Text\TextContentType;

#[CoversClass(DeletePage::class)]
#[CoversClass(ListPages::class)]
final class DeleteAndListPagesTest extends TestCase
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

    public function testListsPagesOrderedByPath(): void
    {
        foreach (['contacts', 'about', 'blog/hello'] as $path) {
            $this->createPage->handle(new CreatePageCommand('text', $path, 'Title', []));
        }

        $paths = array_map(
            static fn(PageMetadata $metadata): string => $metadata->details->path->value,
            new ListPages($this->pages)->handle(),
        );

        $this->assertSame(['about', 'blog/hello', 'contacts'], $paths);
    }

    public function testDeletesPage(): void
    {
        $page = $this->createPage->handle(new CreatePageCommand('text', 'about', 'About', []));

        new DeletePage($this->pages)->handle($page->metadata->id->value);

        $this->assertSame([], $this->pages->allMetadata());
    }

    public function testDeletingUnknownPageFails(): void
    {
        $this->expectException(PageNotFound::class);

        new DeletePage($this->pages)->handle('01ARZ3NDEKTSV4RRFFQ69G5FAV');
    }
}
