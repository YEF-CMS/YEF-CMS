<?php

declare(strict_types=1);

namespace Yef\Admin\Modules;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yef\Admin\Http\Redirect;
use Yef\Admin\NotFound\NotFoundHandler;
use Yef\Application\Module\DisableModule;
use Yef\Domain\Module\Exception\ModuleSettingsFailed;
use Yef\Domain\Module\ModuleName;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Session\Flash\FlashInterface;

/**
 * Disables a module. Only reachable by POST; the list asks for confirmation in a dialog first.
 *
 * @psalm-api Wired through config/common/routes.php.
 */
final readonly class DisableAction
{
    public function __construct(
        private DisableModule $disableModule,
        private Redirect $redirect,
        private FlashInterface $flash,
        private NotFoundHandler $notFound,
        private CurrentRoute $currentRoute,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $name = ModuleName::fromDirectoryName($this->currentRoute->getArgument('name') ?? '');
            $this->disableModule->handle($name->value);
        } catch (InvalidArgumentException) {
            return $this->notFound->handle($request);
        } catch (ModuleSettingsFailed) {
            $this->flash->set('danger', 'modules.error.settings');

            return $this->redirect->to('admin.modules');
        }

        $this->flash->set('success', 'modules.disabled');

        return $this->redirect->to('admin.modules');
    }
}
