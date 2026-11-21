<?php

declare(strict_types=1);

namespace Yef\Admin\Modules;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yef\Admin\View\AdminView;
use Yef\Application\Module\ListModules;

use function array_map;

/**
 * The installed modules with their states and the buttons that switch them.
 *
 * @psalm-api Wired through config/common/routes.php.
 */
final readonly class ListAction
{
    public function __construct(
        private ListModules $listModules,
        private AdminView $view,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        return $this->view->render($request, 'modules/index', [
            'modules' => array_map(ModuleRow::of(...), $this->listModules->handle()),
        ]);
    }
}
