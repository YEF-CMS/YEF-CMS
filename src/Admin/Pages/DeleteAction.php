<?php

declare(strict_types=1);

namespace Yef\Admin\Pages;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yef\Admin\Http\Redirect;
use Yef\Admin\NotFound\NotFoundHandler;
use Yef\Application\Page\DeletePage;
use Yef\Domain\Page\Exception\PageNotFound;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Session\Flash\FlashInterface;

/**
 * Deletes a page. Only reachable by POST; the list asks for confirmation in a dialog first.
 *
 * @psalm-api Wired through config/common/routes.php.
 */
final readonly class DeleteAction
{
    public function __construct(
        private DeletePage $deletePage,
        private Redirect $redirect,
        private FlashInterface $flash,
        private NotFoundHandler $notFound,
        private CurrentRoute $currentRoute,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $this->deletePage->handle($this->currentRoute->getArgument('id') ?? '');
        } catch (InvalidArgumentException|PageNotFound) {
            return $this->notFound->handle($request);
        }

        $this->flash->set('success', 'pages.deleted');

        return $this->redirect->to('admin.pages');
    }
}
