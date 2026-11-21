<?php

declare(strict_types=1);

namespace Yef\Tests\Admin;

use HttpSoft\Message\ResponseFactory;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Yef\Admin\NotFound\NotFoundHandler;
use Yef\Admin\Pages\EditAction;
use Yef\Admin\Pages\NewAction;
use Yef\Admin\Pages\PageFormErrors;
use Yef\Admin\Pages\PageFormRenderer;
use Yef\Admin\Pages\PreviewAction;
use Yef\Application\Page\CreatePageCommand;
use Yef\Application\Page\PreviewPage;
use Yef\Application\Publishing\RenderPage;
use Yef\Domain\ContentType\ContentTypeDefinition;
use Yef\Domain\ContentType\ContentTypeId;
use Yef\Domain\ContentType\ContentTypeRegistry;
use Yef\Domain\ContentType\Exception\InvalidPayload;
use Yef\Domain\ContentType\Payload;
use Yef\Domain\ContentType\PayloadFactory;
use Yef\Tests\Support\AdminHttpScenario;
use Yef\Tests\Support\FakePageRenderer;
use Yef\Tests\Support\FrozenClock;
use Yef\Tests\Support\SequentialPageIdGenerator;
use YefCms\TextPages\TextContentRenderer;
use YefCms\TextPages\TextPageForm;
use YefCms\TextPages\TextPayloadSerializer;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\Route;

#[CoversClass(PreviewAction::class)]
#[CoversClass(PageFormRenderer::class)]
#[CoversClass(EditAction::class)]
final class PreviewScreenTest extends TestCase
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

    public function testShowsThePageAsTheFormHoldsIt(): void
    {
        $response = $this->preview(['title' => 'About us', 'fields' => ['body' => '<p>Hi</p>']]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertSame('<doc title="About us"><p>Hi</p></doc>', (string) $response->getBody());
    }

    public function testPreviewIsSandboxedSoItsScriptsCannotReachTheAdmin(): void
    {
        $response = $this->preview(['title' => 'x', 'fields' => ['body' => '<script>steal()</script>']]);

        $this->assertSame('sandbox allow-scripts', $response->getHeaderLine('Content-Security-Policy'));
    }

    public function testSavesNothing(): void
    {
        $this->preview(['title' => 'About', 'fields' => ['body' => 'x']]);

        $this->assertSame([], $this->admin->listPages->handle());
        $this->assertSame([], $this->admin->publisher->artifacts);
    }

    public function testTheAddressIsNotNeededAndNotChecked(): void
    {
        $response = $this->preview(['title' => 'About', 'path' => 'Not A Valid Path!', 'fields' => ['body' => 'x']]);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testWrongContentIsExplainedInsteadOfShown(): void
    {
        $types = new ContentTypeRegistry([new ContentTypeDefinition(
            new ContentTypeId('strict'),
            'Strict',
            new TextPageForm(),
            new readonly class implements PayloadFactory {
                #[Override]
                public function fromInput(array $input): Payload
                {
                    throw new InvalidPayload('The body is not acceptable.');
                }
            },
            new TextPayloadSerializer(),
            new TextContentRenderer(),
        )]);
        $action = new PreviewAction(
            new PreviewPage($types, new SequentialPageIdGenerator(), new FrozenClock(), new RenderPage($types, new FakePageRenderer())),
            new PageFormErrors(),
            $this->admin->view,
            new NotFoundHandler(new ResponseFactory()),
            new ResponseFactory(),
        );

        $response = $action($this->admin->request('POST', '/pages/preview', ['title' => 'About'])->withQueryParams(['type' => 'strict']));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('The preview cannot be built', (string) $response->getBody());
        $this->assertStringContainsString('The body is not acceptable.', (string) $response->getBody());
    }

    public function testUnknownTypeIsNotFound(): void
    {
        $this->assertSame(404, $this->preview(['title' => 'x'], 'gallery')->getStatusCode());
    }

    public function testMissingTypeIsRefused(): void
    {
        $this->assertSame(422, $this->preview(['title' => 'x'], '')->getStatusCode());
    }

    public function testNewPageFormLoadsTheEditorAndOffersAPreview(): void
    {
        $html = (string) $this->newForm()->getBody();

        $this->assertStringContainsString('/admin/assets/vendor/ckeditor5/ckeditor5.umd.js', $html);
        $this->assertStringContainsString('/admin/assets/vendor/ckeditor5/ckeditor5.css', $html);
        $this->assertStringContainsString('/admin/assets/editor.js', $html);
        $this->assertStringContainsString('formaction="/route/admin.pages.preview"', $html);
        $this->assertStringContainsString('formtarget="_blank"', $html);
    }

    public function testNewPageStartsInTheStandardFormat(): void
    {
        $this->assertStringContainsString('data-default="standard"', (string) $this->newForm()->getBody());
    }

    public function testExistingPageOpensInFullHtmlSoNothingIsCutOut(): void
    {
        $page = $this->admin->createPage->handle(new CreatePageCommand('text', 'about', 'About', ['body' => '<iframe></iframe>']));
        $id = $page->metadata->id->value;

        $html = (string) $this->editForm($id)->getBody();

        $this->assertStringContainsString('data-default="full"', $html);
    }

    public function testEditorFindsTheStylesOfTheSiteInThePublicPartNextToTheAdmin(): void
    {
        $html = (string) $this->newForm()->getBody();

        $this->assertStringContainsString('data-site-css="/assets/editor.css"', $html);
        $this->assertStringContainsString('data-fallback-css="/admin/assets/editor-content.css"', $html);
    }

    /**
     * @param array<string, mixed> $form
     */
    private function preview(array $form, string $type = 'text'): ResponseInterface
    {
        $action = new PreviewAction(
            $this->admin->previewPage,
            new PageFormErrors(),
            $this->admin->view,
            new NotFoundHandler(new ResponseFactory()),
            new ResponseFactory(),
        );

        return $action($this->admin->request('POST', '/pages/preview', $form)->withQueryParams(['type' => $type]));
    }

    private function editForm(string $id): ResponseInterface
    {
        $route = new CurrentRoute();
        /** @psalm-suppress InternalMethod The router fills this in itself; tests have to do it by hand. */
        $route->setRouteWithArguments(Route::get('/pages/{id}/edit'), ['id' => $id]);

        $action = new EditAction(
            $this->admin->getPage,
            $this->admin->updatePage,
            new PageFormRenderer($this->admin->view),
            new PageFormErrors(),
            $this->admin->view,
            $this->admin->redirect,
            $this->admin->flash,
            new NotFoundHandler(new ResponseFactory()),
            $route,
        );

        return $action($this->admin->request('GET', '/pages/' . $id . '/edit'));
    }

    private function newForm(): ResponseInterface
    {
        $action = new NewAction(
            $this->admin->listContentTypes,
            $this->admin->createPage,
            new PageFormRenderer($this->admin->view),
            new PageFormErrors(),
            $this->admin->view,
            $this->admin->redirect,
            $this->admin->flash,
        );

        $request = $this->admin->request('GET', '/pages/new');

        return $action($request);
    }
}
