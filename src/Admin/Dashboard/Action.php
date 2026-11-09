<?php

declare(strict_types=1);

namespace Yef\Admin\Dashboard;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yef\Admin\View\AdminView;
use Yef\Application\Page\SummarizePages;

/**
 * The first screen after signing in: how many pages the site has.
 *
 * @psalm-api Wired through config/common/routes.php.
 */
final readonly class Action
{
    public function __construct(
        private SummarizePages $summarizePages,
        private AdminView $view,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        return $this->view->render($request, 'dashboard', ['summary' => $this->summarizePages->handle()]);
    }
}
