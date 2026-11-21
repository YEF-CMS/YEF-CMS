<?php

declare(strict_types=1);

namespace Yef\Admin\Dashboard;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yef\Admin\View\AdminView;
use Yef\Application\Page\SummarizePages;
use Yef\Application\Publishing\PlanChanges;

/**
 * The first screen after signing in: how many pages the site has and whether changes wait to be applied.
 *
 * @psalm-api Wired through config/common/routes.php.
 */
final readonly class Action
{
    public function __construct(
        private SummarizePages $summarizePages,
        private PlanChanges $planChanges,
        private AdminView $view,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        return $this->view->render($request, 'dashboard', [
            'summary' => $this->summarizePages->handle(),
            'pending' => $this->planChanges->handle()->count(),
        ]);
    }
}
