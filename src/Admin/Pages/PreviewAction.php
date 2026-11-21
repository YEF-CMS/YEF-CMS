<?php

declare(strict_types=1);

namespace Yef\Admin\Pages;

use InvalidArgumentException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yef\Admin\Http\Form;
use Yef\Admin\NotFound\NotFoundHandler;
use Yef\Admin\View\AdminView;
use Yef\Application\Page\PreviewPage;
use Yef\Application\Page\PreviewPageCommand;
use Yef\Domain\ContentType\Exception\InvalidPayload;
use Yef\Domain\ContentType\Exception\UnknownContentType;

use function is_string;

/**
 * Shows a page as the form holds it, before it is saved. Only reachable by POST, from the page form.
 *
 * The preview is a sandboxed document: scripts that belong to the page run, but cannot reach the admin.
 *
 * @psalm-api Wired through config/common/routes.php.
 */
final readonly class PreviewAction
{
    private const string SANDBOX = 'sandbox allow-scripts';

    public function __construct(
        private PreviewPage $previewPage,
        private PageFormErrors $errors,
        private AdminView $view,
        private NotFoundHandler $notFound,
        private ResponseFactoryInterface $responseFactory,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $form = Form::of($request);

        try {
            $html = $this->previewPage->handle(new PreviewPageCommand(
                $this->typeAsked($request),
                trim($form->text('title')),
                $form->section('fields')->strings(),
                trim($form->text('meta_title')),
                trim($form->text('meta_description')),
            ));
        } catch (UnknownContentType) {
            return $this->notFound->handle($request);
        } catch (InvalidArgumentException|InvalidPayload $failure) {
            $messages = $this->errors->of($failure) ?? [];

            return $this->view->render($request, 'pages/preview_error', ['messages' => $messages], 422);
        }

        $response = $this->responseFactory
            ->createResponse(200)
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withHeader('Content-Security-Policy', self::SANDBOX);
        $response->getBody()->write($html);

        return $response;
    }

    private function typeAsked(ServerRequestInterface $request): string
    {
        /** @psalm-suppress MixedAssignment The type is checked right below. */
        $asked = $request->getQueryParams()['type'] ?? null;

        return is_string($asked) ? $asked : '';
    }
}
