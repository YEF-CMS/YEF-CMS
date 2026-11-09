<?php

declare(strict_types=1);

namespace Yef\Admin\Login;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yef\Admin\Authentication\UserIdentity;
use Yef\Admin\Http\Form;
use Yef\Admin\Http\Redirect;
use Yef\Admin\View\AdminView;
use Yef\Application\User\Authenticate;
use Yef\Application\User\PrepareSetup;
use Yef\Domain\User\User;
use Yiisoft\User\CurrentUser;

/**
 * Shows the sign-in form and signs the user in. A site without users is sent to the setup first.
 *
 * @psalm-api Wired through config/common/routes.php.
 */
final readonly class Action
{
    public function __construct(
        private PrepareSetup $prepareSetup,
        private Authenticate $authenticate,
        private CurrentUser $currentUser,
        private AdminView $view,
        private Redirect $redirect,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->prepareSetup->handle()) {
            return $this->redirect->to('admin.setup');
        }

        if (!$this->currentUser->isGuest()) {
            return $this->redirect->to('admin.dashboard');
        }

        if ($request->getMethod() !== 'POST') {
            return $this->form($request, '', null);
        }

        $form = Form::of($request);
        $login = $form->text('login');

        $user = $this->authenticate->handle($login, $form->text('password'));
        if (!$user instanceof User) {
            return $this->form($request, $login, 'login.failed');
        }

        $this->currentUser->login(new UserIdentity($user->id->value));

        return $this->redirect->to('admin.dashboard');
    }

    private function form(ServerRequestInterface $request, string $login, ?string $error): ResponseInterface
    {
        return $this->view->render($request, 'login', ['login' => $login, 'error' => $error]);
    }
}
