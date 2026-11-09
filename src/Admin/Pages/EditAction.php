<?php

declare(strict_types=1);

namespace Yef\Admin\Pages;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yef\Admin\Http\Redirect;
use Yef\Admin\NotFound\NotFoundHandler;
use Yef\Admin\View\AdminView;
use Yef\Application\Page\GetPage;
use Yef\Application\Page\PageToEdit;
use Yef\Application\Page\UpdatePage;
use Yef\Application\Page\UpdatePageCommand;
use Yef\Domain\ContentType\ContentTypeDefinition;
use Yef\Domain\ContentType\Exception\InvalidPayload;
use Yef\Domain\Page\Exception\PageNotFound;
use Yef\Domain\Page\Exception\PathAlreadyTaken;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Session\Flash\FlashInterface;

/**
 * Edits a page. A page whose content type is not available (its module is off) is shown but cannot be changed.
 *
 * @psalm-api Wired through config/common/routes.php.
 */
final readonly class EditAction
{
    public function __construct(
        private GetPage $getPage,
        private UpdatePage $updatePage,
        private PageFormRenderer $formRenderer,
        private PageFormErrors $errors,
        private AdminView $view,
        private Redirect $redirect,
        private FlashInterface $flash,
        private NotFoundHandler $notFound,
        private CurrentRoute $currentRoute,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $id = $this->currentRoute->getArgument('id');
        $toEdit = $id === null ? null : $this->load($id);
        if (!$toEdit instanceof PageToEdit) {
            return $this->notFound->handle($request);
        }

        $type = $toEdit->type;
        if (!$type instanceof ContentTypeDefinition) {
            return $this->view->render($request, 'pages/unavailable', ['page' => $toEdit->page->metadata]);
        }

        $pageId = $toEdit->page->metadata->id->value;
        if ($request->getMethod() !== 'POST') {
            return $this->formRenderer->render($request, $type, SubmittedPage::of($toEdit->page, $type), [], $pageId);
        }

        $submitted = SubmittedPage::fromRequest($request, $type);
        try {
            $this->updatePage->handle(new UpdatePageCommand(
                $pageId,
                $submitted->path,
                $submitted->title,
                $submitted->fields,
                $submitted->published,
                $submitted->metaTitle,
                $submitted->metaDescription,
            ));
        } catch (InvalidArgumentException|PathAlreadyTaken|InvalidPayload $failure) {
            return $this->formRenderer->render($request, $type, $submitted, $this->errors->of($failure) ?? [], $pageId);
        }

        $this->flash->set('success', 'pages.saved');

        return $this->redirect->to('admin.pages');
    }

    private function load(string $id): ?PageToEdit
    {
        try {
            return $this->getPage->handle($id);
        } catch (InvalidArgumentException|PageNotFound) {
            return null;
        }
    }
}
