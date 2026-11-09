<?php

declare(strict_types=1);

namespace Yef\Admin\Pages;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yef\Admin\View\AdminView;
use Yef\Domain\ContentType\ContentTypeDefinition;

/**
 * Draws the page form for creating a page or editing one.
 */
final readonly class PageFormRenderer
{
    public function __construct(private AdminView $view) {}

    /**
     * @param array<string, array{key: string|null, params: array<string, int>, text: string|null}> $errors
     * @param string|null $pageId Null when a new page is being created.
     */
    public function render(
        ServerRequestInterface $request,
        ContentTypeDefinition $type,
        SubmittedPage $values,
        array $errors,
        ?string $pageId,
    ): ResponseInterface {
        return $this->view->render($request, 'pages/form', [
            'type' => $type,
            'fields' => $type->form->fields(),
            'values' => $values,
            'errors' => $errors,
            'pageId' => $pageId,
        ]);
    }
}
