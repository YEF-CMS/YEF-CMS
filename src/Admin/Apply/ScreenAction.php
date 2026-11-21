<?php

declare(strict_types=1);

namespace Yef\Admin\Apply;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yef\Admin\View\AdminView;
use Yef\Application\Publishing\PlanChanges;

/**
 * The screen that lists the changes waiting to be applied; a script on it applies them one page at a time.
 *
 * @psalm-api Wired through config/common/routes.php.
 */
final readonly class ScreenAction
{
    public function __construct(
        private PlanChanges $planChanges,
        private AdminView $view,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        return $this->view->render($request, 'apply/index', ['changes' => $this->planChanges->handle()->changes]);
    }
}
