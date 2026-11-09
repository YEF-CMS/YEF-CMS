<?php

declare(strict_types=1);

namespace Yef\Admin\Pages;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yef\Admin\View\AdminView;
use Yef\Application\Page\ListContentTypes;
use Yef\Application\Page\ListPages;

/**
 * The list of the site's pages.
 *
 * @psalm-api Wired through config/common/routes.php.
 */
final readonly class ListAction
{
    public function __construct(
        private ListPages $listPages,
        private ListContentTypes $listContentTypes,
        private AdminView $view,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $types = $this->listContentTypes->handle();
        $titles = [];
        foreach ($types as $type) {
            $titles[$type->id->value] = $type->title;
        }

        return $this->view->render($request, 'pages/index', [
            'pages' => $this->listPages->handle(),
            'types' => $types,
            'typeTitles' => $titles,
        ]);
    }
}
