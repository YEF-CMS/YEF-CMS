<?php

declare(strict_types=1);

namespace Yef\Tests\Admin;

use DateTimeImmutable;
use HttpSoft\Message\ResponseFactory;
use InvalidArgumentException;
use LogicException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Yef\Admin\NotFound\NotFoundHandler;
use Yef\Admin\Pages\DeleteAction;
use Yef\Admin\Pages\EditAction;
use Yef\Admin\Pages\ListAction;
use Yef\Admin\Pages\NewAction;
use Yef\Admin\Pages\PageFormErrors;
use Yef\Admin\Pages\PageFormRenderer;
use Yef\Admin\Pages\SubmittedPage;
use Yef\Application\Page\CreatePageCommand;
use Yef\Application\Page\ListContentTypes;
use Yef\Domain\ContentType\ContentTypeDefinition;
use Yef\Domain\ContentType\ContentTypeId;
use Yef\Domain\ContentType\ContentTypeRegistry;
use Yef\Domain\ContentType\Exception\InvalidPayload;
use Yef\Domain\ContentType\PayloadData;
use Yef\Domain\ContentType\UnavailablePayload;
use Yef\Domain\Page\Exception\InvalidPageDetails;
use Yef\Domain\Page\Exception\PathAlreadyTaken;
use Yef\Domain\Page\Page;
use Yef\Domain\Page\PageDetails;
use Yef\Domain\Page\PageId;
use Yef\Domain\Page\PageMetadata;
use Yef\Domain\Page\PagePath;
use Yef\Tests\Support\AdminHttpScenario;
use Yef\Tests\Support\FakeCsrfToken;
use YefCms\TextPages\TextContentRenderer;
use YefCms\TextPages\TextContentType;
use YefCms\TextPages\TextPageForm;
use YefCms\TextPages\TextPayloadFactory;
use YefCms\TextPages\TextPayloadSerializer;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\Route;

#[CoversClass(ListAction::class)]
#[CoversClass(NewAction::class)]
#[CoversClass(EditAction::class)]
#[CoversClass(DeleteAction::class)]
#[CoversClass(PageFormRenderer::class)]
#[CoversClass(PageFormErrors::class)]
#[CoversClass(SubmittedPage::class)]
final class PagesScreensTest extends TestCase
{
    private AdminHttpScenario $admin;

    #[Override]
    protected function setUp(): void
    {
        $this->admin = new AdminHttpScenario();
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->admin->cleanUp();
    }

    public function testEmptyListSaysThereAreNoPagesAndOffersToCreateOne(): void
    {
        $html = $this->body($this->listAction()($this->admin->request('GET', '/pages')));

        $this->assertStringContainsString('The site has no pages yet.', $html);
        $this->assertStringContainsString('href="/route/admin.pages.new"', $html);
    }

    public function testListShowsEveryPageWithItsTypeStatusAndAddress(): void
    {
        $this->admin->createPage->handle(new CreatePageCommand('text', 'about', 'About us', ['body' => 'x'], published: true));
        $this->admin->createPage->handle(new CreatePageCommand('text', 'secret', 'Secret', ['body' => 'x']));

        $html = $this->body($this->listAction()($this->admin->request('GET', '/pages')));

        $this->assertStringContainsString('About us', $html);
        $this->assertStringContainsString('<a href="/about/" target="_blank" rel="noopener"><code>/about/</code></a>', $html);
        $this->assertStringContainsString('<code>/secret/</code>', $html);
        $this->assertStringNotContainsString('href="/secret/"', $html);
        $this->assertStringContainsString('Text page', $html);
        $this->assertStringContainsString('Published', $html);
        $this->assertStringContainsString('Draft', $html);
    }

    public function testEachRowAsksForConfirmationBeforeDeletingInADialog(): void
    {
        $page = $this->admin->createPage->handle(new CreatePageCommand('text', 'about', 'About us', ['body' => 'x']));
        $id = $page->metadata->id->value;

        $html = $this->body($this->listAction()($this->admin->request('GET', '/pages')));

        $this->assertStringContainsString('data-bs-target="#delete-' . $id . '"', $html);
        $this->assertStringContainsString('id="delete-' . $id . '"', $html);
        $this->assertStringContainsString('action="/route/admin.pages.delete"', $html);
        $this->assertStringContainsString('Delete the page &quot;About us&quot;?', $html);
        $this->assertStringContainsString('name="_csrf" value="' . FakeCsrfToken::VALUE . '"', $html);
    }

    public function testPageOfAnUnavailableTypeIsMarkedAndCannotBeOpenedForEditing(): void
    {
        $this->admin->pages->save(new Page(
            PageMetadata::draft(
                new PageId('01JC0000000000000000000001'),
                new ContentTypeId('gallery'),
                new PageDetails(new PagePath('photos'), 'Photos'),
                new DateTimeImmutable('2026-11-09 12:00:00 UTC'),
            ),
            new UnavailablePayload(new ContentTypeId('gallery'), new PayloadData(['photos' => '[]'])),
        ));
        $html = $this->body($this->listAction()($this->admin->request('GET', '/pages')));

        $this->assertStringContainsString('Type unavailable', $html);
        $this->assertStringNotContainsString('href="/route/admin.pages.edit"', $html);

        $edit = $this->body($this->editAction('01JC0000000000000000000001')($this->editRequest('01JC0000000000000000000001')));
        $this->assertStringContainsString('cannot be edited', $edit);
        $this->assertStringNotContainsString('<form method="post" action="/route/admin.pages.edit"', $edit);
        $this->assertStringContainsString('id="delete-01JC0000000000000000000001"', $edit);
    }

    public function testTitlesAreEscapedInTheList(): void
    {
        $this->admin->createPage->handle(new CreatePageCommand('text', 'x', '<script>alert(1)</script>', ['body' => '']));

        $html = $this->body($this->listAction()($this->admin->request('GET', '/pages')));

        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    public function testNewPageGoesStraightToTheFormWhenThereIsOneType(): void
    {
        $html = $this->body($this->newAction()($this->admin->request('GET', '/pages/new')));

        $this->assertStringContainsString('New page', $html);
        $this->assertStringContainsString('Type: Text page', $html);
        $this->assertStringContainsString('name="fields[body]"', $html);
        $this->assertStringContainsString('name="title"', $html);
        $this->assertStringContainsString('name="path"', $html);
        $this->assertStringContainsString('name="published"', $html);
        $this->assertStringContainsString('name="_csrf" value="' . FakeCsrfToken::VALUE . '"', $html);
    }

    public function testSeveralTypesAreOfferedToChooseFrom(): void
    {
        $action = $this->newAction($this->twoTypes());

        $chooser = $this->body($action($this->admin->request('GET', '/pages/new')));
        $this->assertStringContainsString('What kind of page is it?', $chooser);
        $this->assertStringContainsString('Text page', $chooser);
        $this->assertStringContainsString('News item', $chooser);

        $form = $this->body($action($this->admin->request('GET', '/pages/new')->withQueryParams(['type' => 'news'])));
        $this->assertStringContainsString('Type: News item', $form);
    }

    public function testNoTypesMeansNoPageCanBeCreated(): void
    {
        $html = $this->body($this->newAction(new ContentTypeRegistry([]))($this->admin->request('GET', '/pages/new')));

        $this->assertStringContainsString('no module that adds a content type is enabled', $html);
    }

    public function testCreatingAPageSavesItAndSaysSoOnce(): void
    {
        $response = $this->newAction()($this->admin->request('POST', '/pages/new', [
            'title' => ' Contacts ',
            'path' => '/contacts/',
            'fields' => ['body' => '<p>Call us</p>'],
            'meta_title' => 'Contact',
            'meta_description' => 'How to reach us',
            'published' => '1',
        ]));

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('/route/admin.pages', $response->getHeaderLine('Location'));
        $this->assertSame('contacts', $this->onlyPage()->details->path->value);
        $this->assertSame('contacts', $this->onlyPage()->details->path->value);
        $this->assertSame('Contacts', $this->onlyPage()->details->title);
        $this->assertSame('Contact', $this->onlyPage()->details->metaTitle);
        $this->assertTrue($this->onlyPage()->isPublished());

        $list = $this->body($this->listAction()($this->admin->request('GET', '/pages')));
        $this->assertStringContainsString('The page is created.', $list);
        $again = $this->body($this->listAction()($this->admin->request('GET', '/pages')));
        $this->assertStringNotContainsString('The page is created.', $again);
    }

    public function testPageIsADraftUnlessTheBoxIsTicked(): void
    {
        $this->newAction()($this->admin->request('POST', '/pages/new', ['title' => 'Draft', 'path' => 'draft', 'fields' => ['body' => '']]));

        $this->assertFalse($this->onlyPage()->isPublished());
    }

    public function testMistakesAreShownNextToTheirFieldAndNothingIsLost(): void
    {
        $response = $this->newAction()($this->admin->request('POST', '/pages/new', [
            'title' => 'Hello',
            'path' => 'Bad Path',
            'fields' => ['body' => '<p>My long text</p>'],
        ]));
        $html = $this->body($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Use lowercase latin letters, digits and hyphens', $html);
        $this->assertStringContainsString('value="Hello"', $html);
        $this->assertStringContainsString('value="Bad Path"', $html);
        $this->assertStringContainsString('&lt;p&gt;My long text&lt;/p&gt;', $html);
        $this->assertSame([], $this->admin->pages->allMetadata());
    }

    public function testEmptyTitleIsRefused(): void
    {
        $html = $this->body($this->newAction()($this->admin->request('POST', '/pages/new', ['title' => '  ', 'path' => 'x', 'fields' => ['body' => '']])));

        $this->assertStringContainsString('Enter a title.', $html);
        $this->assertSame([], $this->admin->pages->allMetadata());
    }

    public function testReservedAddressIsRefused(): void
    {
        $html = $this->body($this->newAction()($this->admin->request('POST', '/pages/new', ['title' => 'Admin', 'path' => 'admin/panel', 'fields' => ['body' => '']])));

        $this->assertStringContainsString('keeps for itself', $html);
    }

    public function testAddressOfAnotherPageIsRefused(): void
    {
        $this->admin->createPage->handle(new CreatePageCommand('text', 'about', 'About', ['body' => '']));

        $html = $this->body($this->newAction()($this->admin->request('POST', '/pages/new', ['title' => 'Other', 'path' => 'about', 'fields' => ['body' => '']])));

        $this->assertStringContainsString('Another page already has this address.', $html);
        $this->assertCount(1, $this->admin->pages->allMetadata());
    }

    public function testHomePageHasAnEmptyAddress(): void
    {
        $this->newAction()($this->admin->request('POST', '/pages/new', ['title' => 'Home', 'path' => '', 'fields' => ['body' => 'Hi'], 'published' => '1']));

        $this->assertTrue($this->onlyPage()->details->path->isHome());
    }

    public function testEditFormIsFilledWithThePageAndEscapesItsContent(): void
    {
        $page = $this->admin->createPage->handle(new CreatePageCommand(
            'text',
            'about',
            'About "us"',
            ['body' => '</textarea><script>alert(1)</script>'],
            'Meta',
            'Description',
            true,
        ));

        $html = $this->body($this->editAction($page->metadata->id->value)($this->editRequest($page->metadata->id->value)));

        $this->assertStringContainsString('value="About &quot;us&quot;"', $html);
        $this->assertStringContainsString('value="about"', $html);
        $this->assertStringContainsString('&lt;/textarea&gt;&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertMatchesRegularExpression('/id="published"[^>]*checked/', $html);
        $this->assertStringContainsString('action="/route/admin.pages.edit"', $html);
    }

    public function testEditingSavesTheChangesAndSaysSo(): void
    {
        $page = $this->admin->createPage->handle(new CreatePageCommand('text', 'about', 'About', ['body' => 'Old']));
        $id = $page->metadata->id->value;

        $response = $this->editAction($id)($this->editRequest($id, 'POST', [
            'title' => 'About us',
            'path' => 'about-us',
            'fields' => ['body' => 'New'],
            'published' => '1',
        ]));

        $this->assertSame('/route/admin.pages', $response->getHeaderLine('Location'));
        $saved = $this->admin->getPage->handle($id)->page;
        $this->assertSame('About us', $saved->metadata->details->title);
        $this->assertSame('about-us', $saved->metadata->details->path->value);
        $this->assertTrue($saved->metadata->isPublished());
        $this->assertStringContainsString('The page is saved.', $this->body($this->listAction()($this->admin->request('GET', '/pages'))));
    }

    public function testUntickingPublishedMakesThePageADraft(): void
    {
        $page = $this->admin->createPage->handle(new CreatePageCommand('text', 'about', 'About', ['body' => ''], published: true));

        $this->editAction($page->metadata->id->value)($this->editRequest($page->metadata->id->value, 'POST', ['title' => 'About', 'path' => 'about', 'fields' => ['body' => '']]));

        $this->assertFalse($this->onlyPage()->isPublished());
    }

    public function testEditingToAnAddressOfAnotherPageShowsTheMistake(): void
    {
        $this->admin->createPage->handle(new CreatePageCommand('text', 'about', 'About', ['body' => '']));
        $other = $this->admin->createPage->handle(new CreatePageCommand('text', 'blog', 'Blog', ['body' => '']));

        $html = $this->body($this->editAction($other->metadata->id->value)($this->editRequest($other->metadata->id->value, 'POST', ['title' => 'Blog', 'path' => 'about', 'fields' => ['body' => '']])));

        $this->assertStringContainsString('Another page already has this address.', $html);
        $this->assertSame('blog', $this->admin->getPage->handle($other->metadata->id->value)->page->metadata->details->path->value);
    }

    public function testUnknownAndMalformedPageIdsAreNotFound(): void
    {
        $this->assertSame(404, $this->editAction('01JC0000000000000000000009')($this->editRequest('01JC0000000000000000000009'))->getStatusCode());
        $this->assertSame(404, $this->editAction('nonsense')($this->editRequest('nonsense'))->getStatusCode());
        $this->assertSame(404, $this->deleteAction('01JC0000000000000000000009')($this->editRequest('01JC0000000000000000000009', 'POST'))->getStatusCode());
        $this->assertSame(404, $this->deleteAction(null)($this->admin->request('POST', '/pages/x/delete'))->getStatusCode());
    }

    public function testDeletingRemovesThePageAndSaysSo(): void
    {
        $page = $this->admin->createPage->handle(new CreatePageCommand('text', 'about', 'About', ['body' => '']));

        $response = $this->deleteAction($page->metadata->id->value)($this->editRequest($page->metadata->id->value, 'POST'));

        $this->assertSame('/route/admin.pages', $response->getHeaderLine('Location'));
        $this->assertSame([], $this->admin->pages->allMetadata());
        $this->assertStringContainsString('The page is deleted.', $this->body($this->listAction()($this->admin->request('GET', '/pages'))));
    }

    public function testDashboardCountsThePages(): void
    {
        $this->admin->createPage->handle(new CreatePageCommand('text', 'one', 'One', ['body' => ''], published: true));
        $this->admin->createPage->handle(new CreatePageCommand('text', 'two', 'Two', ['body' => '']));
        $this->admin->signIn();

        $response = new \Yef\Admin\Dashboard\Action($this->admin->summarizePages, $this->admin->view)($this->admin->request());
        $html = $this->body($response);

        $this->assertMatchesRegularExpression('/display-6">2<.*?>Pages</s', $html);
        $this->assertMatchesRegularExpression('/display-6">1<.*?>Published</s', $html);
        $this->assertMatchesRegularExpression('/display-6">1<.*?>Drafts</s', $html);
    }

    public function testFormErrorsNameTheFieldAndTheRule(): void
    {
        $errors = new PageFormErrors();

        $this->assertSame(['path'], array_keys($errors->of(PathAlreadyTaken::byPath(new PagePath('about'))) ?? []));
        $this->assertSame('pages.error.path.too_long', $errors->of($this->tooLongPath())['path']['key'] ?? null);
        $this->assertSame('Not a string.', $errors->of(new InvalidPayload('Not a string.'))['payload']['text'] ?? null);
        $this->assertSame(['general'], array_keys($errors->of(new InvalidArgumentException('x')) ?? []));
        $this->assertNull($errors->of(new RuntimeException('disk full')));
    }

    public function testSubmittedFormReadsOnlyTheFieldsOfItsType(): void
    {
        $type = TextContentType::definition();
        $request = $this->admin->request('POST', '/pages/new', [
            'title' => ' T ',
            'path' => ' /a/b/ ',
            'fields' => ['body' => 'text', 'other' => 'ignored'],
            'published' => '0',
        ]);

        $submitted = SubmittedPage::fromRequest($request, $type);

        $this->assertSame('T', $submitted->title);
        $this->assertSame('a/b', $submitted->path);
        $this->assertSame(['body' => 'text'], $submitted->fields);
        $this->assertFalse($submitted->published);
    }

    private function onlyPage(): PageMetadata
    {
        $all = $this->admin->pages->allMetadata();
        $this->assertCount(1, $all);

        return $all[0] ?? throw new LogicException('There is no page.');
    }

    private function tooLongPath(): InvalidPageDetails
    {
        try {
            new PagePath(str_repeat('a', 201));
        } catch (InvalidPageDetails $invalidPageDetails) {
            return $invalidPageDetails;
        }

        $this->fail('A path of 201 characters must be refused.');
    }

    private function listAction(): ListAction
    {
        return new ListAction($this->admin->listPages, $this->admin->listContentTypes, $this->admin->view);
    }

    private function newAction(?ContentTypeRegistry $types = null): NewAction
    {
        return new NewAction(
            $types instanceof ContentTypeRegistry ? new ListContentTypes($types) : $this->admin->listContentTypes,
            $this->admin->createPage,
            new PageFormRenderer($this->admin->view),
            new PageFormErrors(),
            $this->admin->view,
            $this->admin->redirect,
            $this->admin->flash,
        );
    }

    private function editAction(string $id): EditAction
    {
        return new EditAction(
            $this->admin->getPage,
            $this->admin->updatePage,
            new PageFormRenderer($this->admin->view),
            new PageFormErrors(),
            $this->admin->view,
            $this->admin->redirect,
            $this->admin->flash,
            new NotFoundHandler(new ResponseFactory()),
            $this->routeWithId($id),
        );
    }

    private function deleteAction(?string $id): DeleteAction
    {
        return new DeleteAction(
            $this->admin->deletePage,
            $this->admin->redirect,
            $this->admin->flash,
            new NotFoundHandler(new ResponseFactory()),
            $id === null ? new CurrentRoute() : $this->routeWithId($id),
        );
    }

    /**
     * What the router knows about a request for the page with this id.
     */
    private function routeWithId(string $id): CurrentRoute
    {
        $route = new CurrentRoute();
        /** @psalm-suppress InternalMethod The router fills this in itself; tests have to do it by hand. */
        $route->setRouteWithArguments(Route::get('/pages/{id}/edit'), ['id' => $id]);

        return $route;
    }

    /**
     * @param array<string, mixed> $form
     */
    private function editRequest(string $id, string $method = 'GET', array $form = []): \Psr\Http\Message\ServerRequestInterface
    {
        return $this->admin->request($method, '/pages/' . $id . '/edit', $form);
    }

    private function twoTypes(): ContentTypeRegistry
    {
        return new ContentTypeRegistry([
            TextContentType::definition(),
            new ContentTypeDefinition(
                new ContentTypeId('news'),
                'News item',
                new TextPageForm(),
                new TextPayloadFactory(),
                new TextPayloadSerializer(),
                new TextContentRenderer(),
            ),
        ]);
    }

    private function body(ResponseInterface $response): string
    {
        return (string) $response->getBody();
    }
}
