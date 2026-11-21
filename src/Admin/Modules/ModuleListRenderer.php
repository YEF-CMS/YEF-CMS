<?php

declare(strict_types=1);

namespace Yef\Admin\Modules;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yef\Admin\View\AdminView;
use Yef\Application\Module\ListModules;
use Yef\Application\ModuleInstall\ListInstalledModules;
use Yef\Application\ModuleInstall\UpdateCheck;
use Yef\Domain\Module\ModuleStatus;

/**
 * Draws the list of modules, with what is known about where the installed ones came from.
 */
final readonly class ModuleListRenderer
{
    public function __construct(
        private ListModules $listModules,
        private ListInstalledModules $listInstalled,
        private AdminView $view,
    ) {}

    /**
     * @param array<string, UpdateCheck> $checks The result of checking for updates, by module name; empty when not checked.
     */
    public function render(ServerRequestInterface $request, array $checks = [], bool $checked = false): ResponseInterface
    {
        $installed = [];
        foreach ($this->listInstalled->handle() as $module) {
            $installed[$module->name->value] = $module;
        }

        return $this->view->render($request, 'modules/index', [
            'modules' => array_map(
                static fn(ModuleStatus $status): ModuleRow => ModuleRow::of(
                    $status,
                    $installed[$status->id] ?? null,
                    $checks[$status->id] ?? null,
                ),
                $this->listModules->handle(),
            ),
            'checked' => $checked,
            'hasInstalled' => $installed !== [],
        ]);
    }
}
