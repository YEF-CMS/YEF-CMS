<?php

declare(strict_types=1);

namespace Yef\Admin\Modules;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use Yef\Admin\Http\Form;
use Yef\Admin\Http\Redirect;
use Yef\Admin\View\AdminView;
use Yef\Application\ModuleInstall\GetModuleOffer;
use Yef\Application\ModuleInstall\InstallModule;
use Yef\Application\ModuleInstall\ModuleOffer;
use Yef\Application\ModuleInstall\UpdateModule;
use Yef\Application\User\Authenticate;
use Yef\Application\User\FindUser;
use Yef\Domain\User\User;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\User\CurrentUser;

/**
 * Shows a downloaded module for review and, when the admin confirms with their password, installs or updates it.
 * The password is asked again because this changes the code the site runs.
 *
 * @psalm-api Wired through config/common/routes.php.
 */
final readonly class OfferAction
{
    public function __construct(
        private GetModuleOffer $getOffer,
        private InstallModule $installModule,
        private UpdateModule $updateModule,
        private Authenticate $authenticate,
        private CurrentUser $currentUser,
        private FindUser $findUser,
        private InstallFailures $failures,
        private AdminView $view,
        private Redirect $redirect,
        private FlashInterface $flash,
        private CurrentRoute $currentRoute,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $token = $this->currentRoute->getArgument('token') ?? '';

        try {
            $offer = $this->getOffer->handle($token);
        } catch (Throwable $throwable) {
            return $this->failed($throwable);
        }

        if ($request->getMethod() !== 'POST') {
            return $this->show($request, $offer);
        }

        if (!$this->passwordIsRight(Form::of($request)->text('password'))) {
            return $this->show($request, $offer, 'modules.offer.wrong_password', 422);
        }

        try {
            $offer->isUpdate() ? $this->updateModule->handle($token) : $this->installModule->handle($token);
        } catch (Throwable $throwable) {
            $error = $this->failures->explain($throwable);
            if ($error === null) {
                throw $throwable;
            }

            return $this->show($request, $offer, $error['key'], 422);
        }

        $this->flash->set('success', $offer->isUpdate() ? 'modules.updated' : 'modules.installed');

        return $this->redirect->to('admin.modules');
    }

    private function show(ServerRequestInterface $request, ModuleOffer $offer, ?string $errorKey = null, int $status = 200): ResponseInterface
    {
        return $this->view->render($request, 'modules/offer', ['offer' => $offer, 'error' => $errorKey], $status);
    }

    private function passwordIsRight(string $password): bool
    {
        $user = $this->findUser->handle($this->currentUser->getId() ?? '');

        return $user instanceof User && $this->authenticate->handle($user->login->value, $password) instanceof User;
    }

    private function failed(Throwable $failure): ResponseInterface
    {
        $error = $this->failures->explain($failure);
        if ($error === null) {
            throw $failure;
        }

        $this->flash->set('danger', $error['key']);

        return $this->redirect->to('admin.modules.install');
    }
}
