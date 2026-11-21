<?php

declare(strict_types=1);

namespace Yef\Admin\Modules;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yef\Admin\Http\Redirect;
use Yef\Admin\NotFound\NotFoundHandler;
use Yef\Application\Module\EnableModule;
use Yef\Domain\Module\Exception\ModuleCannotBeEnabled;
use Yef\Domain\Module\Exception\ModuleNotFound;
use Yef\Domain\Module\Exception\ModuleSettingsFailed;
use Yef\Domain\Module\ModuleName;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Session\Flash\FlashInterface;

/**
 * Enables a module. Only reachable by POST.
 *
 * @psalm-api Wired through config/common/routes.php.
 */
final readonly class EnableAction
{
    public function __construct(
        private EnableModule $enableModule,
        private Redirect $redirect,
        private FlashInterface $flash,
        private NotFoundHandler $notFound,
        private CurrentRoute $currentRoute,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $name = ModuleName::fromDirectoryName($this->currentRoute->getArgument('name') ?? '');
            $this->enableModule->handle($name->value);
        } catch (InvalidArgumentException|ModuleNotFound) {
            return $this->notFound->handle($request);
        } catch (ModuleCannotBeEnabled) {
            $this->flash->set('danger', 'modules.error.cannot_enable');

            return $this->redirect->to('admin.modules');
        } catch (ModuleSettingsFailed) {
            $this->flash->set('danger', 'modules.error.settings');

            return $this->redirect->to('admin.modules');
        }

        $this->flash->set('success', 'modules.enabled');

        return $this->redirect->to('admin.modules');
    }
}
