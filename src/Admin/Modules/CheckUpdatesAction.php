<?php

declare(strict_types=1);

namespace Yef\Admin\Modules;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yef\Application\ModuleInstall\CheckModuleUpdates;

/**
 * Asks GitHub whether the installed modules have newer releases and shows the list with the answers.
 * Only reachable by POST, because it makes requests to another server.
 *
 * @psalm-api Wired through config/common/routes.php.
 */
final readonly class CheckUpdatesAction
{
    public function __construct(
        private CheckModuleUpdates $checkUpdates,
        private ModuleListRenderer $renderer,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $checks = [];
        foreach ($this->checkUpdates->handle() as $check) {
            $checks[$check->module->name->value] = $check;
        }

        return $this->renderer->render($request, $checks, true);
    }
}
