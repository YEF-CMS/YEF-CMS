<?php

declare(strict_types=1);

namespace Yef\Admin\Modules;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use Yef\Admin\Http\Redirect;
use Yef\Admin\NotFound\NotFoundHandler;
use Yef\Application\ModuleInstall\PrepareModuleUpdate;
use Yef\Domain\Module\ModuleName;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Session\Flash\FlashInterface;

/**
 * Downloads the latest release of an installed module and goes on to show it. Only reachable by POST.
 * Nothing is replaced yet.
 *
 * @psalm-api Wired through config/common/routes.php.
 */
final readonly class PrepareUpdateAction
{
    public function __construct(
        private PrepareModuleUpdate $prepareUpdate,
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
            $offer = $this->prepareUpdate->handle($name->value);
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

        return $this->redirect->toWith('admin.modules.offer', ['token' => $offer->token]);
    }
}
