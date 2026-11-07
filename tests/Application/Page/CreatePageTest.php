<?php

declare(strict_types=1);

namespace Yef\Tests\Application\Page;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Yef\Application\Page\CreatePage;
use Yef\Application\Page\CreatePageCommand;
use Yef\Domain\Page\Exception\PathAlreadyTaken;
use Yef\Domain\Page\PageStatus;
use Yef\Tests\Support\FrozenClock;
use Yef\Tests\Support\InMemoryPageRepository;
use Yef\Tests\Support\SequentialPageIdGenerator;

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
        $this->createPage = new CreatePage($this->pages, new SequentialPageIdGenerator(), new FrozenClock());
    }

    public function testCreatesDraftPageAndStoresIt(): void
    {
        $page = ($this->createPage)(new CreatePageCommand('about', 'About', '<p>Hi</p>', 'Meta', 'Description'));

        $this->assertSame(PageStatus::Draft, $page->status);
        $this->assertSame('about', $page->path->value);
        $this->assertSame('About', $page->content->title);
        $this->assertSame('Meta', $page->content->metaTitle);
        $this->assertSame($page, $this->pages->get($page->id));
    }

    public function testRejectsPathUsedByAnotherPage(): void
    {
        ($this->createPage)(new CreatePageCommand('about', 'About', ''));

        $this->expectException(PathAlreadyTaken::class);

        ($this->createPage)(new CreatePageCommand('about', 'Other', ''));
    }

    public function testRejectsInvalidPathWithoutStoringAnything(): void
    {
        try {
            ($this->createPage)(new CreatePageCommand('Bad Path', 'Title', ''));
            $this->fail('An invalid path must be rejected.');
        } catch (InvalidArgumentException) {
            $this->assertSame([], $this->pages->all());
        }
    }

    public function testRejectsEmptyTitleWithoutStoringAnything(): void
    {
        try {
            ($this->createPage)(new CreatePageCommand('about', ' ', ''));
            $this->fail('An empty title must be rejected.');
        } catch (InvalidArgumentException) {
            $this->assertSame([], $this->pages->all());
        }
    }
}
