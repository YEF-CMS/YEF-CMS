<?php

declare(strict_types=1);

namespace Yef\Admin\Modules;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use Yef\Admin\Http\Redirect;
use Yef\Admin\NotFound\NotFoundHandler;
use Yef\Application\ModuleInstall\RemoveModule;
use Yef\Domain\Module\ModuleName;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Session\Flash\FlashInterface;

/**
 * Removes a module that the installer brought. Only reachable by POST; the list asks for confirmation first.
 *
 * @psalm-api Wired through config/common/routes.php.
 */
final readonly class RemoveAction
{
    public function __construct(
        private RemoveModule $removeModule,
        private InstallFailures $failures,
        private Redirect $redirect,
        private FlashInterface $flash,
        private NotFoundHandler $notFound,
        private CurrentRoute $currentRoute,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $name = ModuleName::fromDirectoryName($this->currentRoute->getArgument('name') ?? '');
            $this->removeModule->handle($name->value);
        } catch (InvalidArgumentException) {
            return $this->notFound->handle($request);
        } catch (Throwable $failure) {
            $error = $this->failures->explain($failure);
            if ($error === null) {
                throw $failure;
            }

            $this->flash->set('danger', $error['key']);

            return $this->redirect->to('admin.modules');
        }

        $this->flash->set('success', 'modules.removed');

        return $this->redirect->to('admin.modules');
    }
}
