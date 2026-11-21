<?php

declare(strict_types=1);

namespace Yef\Tests\Application\Page;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Yef\Application\Page\CreatePage;
use Yef\Application\Page\CreatePageCommand;
use Yef\Application\Page\GetPage;
use Yef\Application\Page\ListContentTypes;
use Yef\Application\Page\PageSummary;
use Yef\Application\Page\PageToEdit;
use Yef\Application\Page\SummarizePages;
use Yef\Domain\ContentType\ContentTypeId;
use Yef\Domain\ContentType\ContentTypeRegistry;
use Yef\Domain\ContentType\PayloadData;
use Yef\Domain\ContentType\UnavailablePayload;
use Yef\Domain\Page\Exception\PageNotFound;
use Yef\Domain\Page\Page;
use Yef\Tests\Support\FrozenClock;
use Yef\Tests\Support\InMemoryPageRepository;
use Yef\Tests\Support\SequentialPageIdGenerator;
use Yef\Tests\Support\TestTexts;
use Yef\Tests\Support\TextPages;
use YefCms\TextPages\TextContentType;

#[CoversClass(GetPage::class)]
#[CoversClass(PageToEdit::class)]
#[CoversClass(ListContentTypes::class)]
#[CoversClass(SummarizePages::class)]
#[CoversClass(PageSummary::class)]
#[CoversClass(CreatePage::class)]
final class PageScreensScenariosTest extends TestCase
{
    private InMemoryPageRepository $pages;

    private CreatePage $createPage;

    #[Override]
    protected function setUp(): void
    {
        $this->pages = new InMemoryPageRepository();
        $this->createPage = new CreatePage(
            $this->pages,
            TextContentType::registry(TestTexts::textModule()),
            new SequentialPageIdGenerator(),
            new FrozenClock(),
        );
    }

    public function testCreatingWithThePublishedFlagSavesAPublishedPageInOneStep(): void
    {
        $page = $this->createPage->handle(new CreatePageCommand('text', 'about', 'About', ['body' => 'x'], published: true));

        $this->assertTrue($page->metadata->isPublished());
        $this->assertTrue($this->pages->get($page->metadata->id)->metadata->isPublished());
    }

    public function testPagesAreDraftsUnlessAskedOtherwise(): void
    {
        $page = $this->createPage->handle(new CreatePageCommand('text', 'about', 'About', ['body' => 'x']));

        $this->assertFalse($page->metadata->isPublished());
    }

    public function testLoadsAPageWithTheDefinitionOfItsType(): void
    {
        $page = $this->createPage->handle(new CreatePageCommand('text', 'about', 'About', ['body' => '<p>Hi</p>']));

        $toEdit = new GetPage($this->pages, TextContentType::registry(TestTexts::textModule()))->handle($page->metadata->id->value);

        $this->assertEquals($page, $toEdit->page);
        $this->assertTrue($toEdit->canBeEdited());
        $this->assertSame('Text page', $toEdit->type?->title);
    }

    public function testPageWhoseTypeIsNotEnabledIsLoadedButCannotBeEdited(): void
    {
        $page = $this->createPage->handle(new CreatePageCommand('text', 'about', 'About', ['body' => 'x']));

        $toEdit = new GetPage($this->pages, new ContentTypeRegistry([]))->handle($page->metadata->id->value);

        $this->assertFalse($toEdit->canBeEdited());
        $this->assertNull($toEdit->type);
    }

    public function testUnavailablePayloadPagesAreLoadedToo(): void
    {
        $page = new Page(
            new TextPages()->page('01JC0000000000000000000001', 'photos', 'x')->metadata,
            new UnavailablePayload(new ContentTypeId('gallery'), new PayloadData(['photos' => '[]'])),
        );
        $this->pages->save($page);

        $toEdit = new GetPage($this->pages, TextContentType::registry(TestTexts::textModule()))->handle('01JC0000000000000000000001');

        $this->assertInstanceOf(UnavailablePayload::class, $toEdit->page->payload);
    }

    public function testUnknownPageIsReported(): void
    {
        $this->expectException(PageNotFound::class);

        new GetPage($this->pages, TextContentType::registry(TestTexts::textModule()))->handle('01JC0000000000000000000009');
    }

    public function testAnIdThatIsNotAnIdIsReportedAsInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new GetPage($this->pages, TextContentType::registry(TestTexts::textModule()))->handle('not-an-id');
    }

    public function testListsTheTypesOfTheEnabledModules(): void
    {
        $types = new ListContentTypes(TextContentType::registry(TestTexts::textModule()))->handle();

        $this->assertSame(['text'], array_map(static fn(\Yef\Domain\ContentType\ContentTypeDefinition $type): string => $type->id->value, $types));
    }

    public function testSummarizesPagesByStatus(): void
    {
        $this->createPage->handle(new CreatePageCommand('text', 'one', 'One', ['body' => ''], published: true));
        $this->createPage->handle(new CreatePageCommand('text', 'two', 'Two', ['body' => '']));
        $this->createPage->handle(new CreatePageCommand('text', 'three', 'Three', ['body' => '']));

        $summary = new SummarizePages($this->pages)->handle();

        $this->assertSame(3, $summary->total);
        $this->assertSame(1, $summary->published);
        $this->assertSame(2, $summary->drafts());
    }

    public function testEmptySiteHasNoPages(): void
    {
        $summary = new SummarizePages($this->pages)->handle();

        $this->assertSame(0, $summary->total);
        $this->assertSame(0, $summary->drafts());
    }
}
