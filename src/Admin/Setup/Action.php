<?php

declare(strict_types=1);

namespace Yef\Admin\Setup;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yef\Admin\Authentication\UserIdentity;
use Yef\Admin\Http\Form;
use Yef\Admin\Http\Redirect;
use Yef\Admin\View\AdminView;
use Yef\Application\User\CompleteSetup;
use Yef\Application\User\InvalidSetupToken;
use Yef\Application\User\PasswordPolicy;
use Yef\Application\User\PrepareSetup;
use Yef\Application\User\SetupNotAllowed;
use Yef\Application\User\WeakPassword;
use Yef\Domain\User\Exception\LoginAlreadyTaken;
use Yiisoft\User\CurrentUser;

/**
 * First-run setup: creates the first user, for someone who can read the setup code from the server's files.
 *
 * @psalm-api Wired through config/common/routes.php.
 */
final readonly class Action
{
    public function __construct(
        private PrepareSetup $prepareSetup,
        private CompleteSetup $completeSetup,
        private PasswordPolicy $passwordPolicy,
        private CurrentUser $currentUser,
        private AdminView $view,
        private Redirect $redirect,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->prepareSetup->handle()) {
            return $this->redirect->to('admin.login');
        }

        if ($request->getMethod() !== 'POST') {
            return $this->form($request, '', null);
        }

        $form = Form::of($request);
        $login = $form->text('login');

        try {
            $user = $this->completeSetup->handle($login, $form->text('password'), $form->text('code'));
        } catch (SetupNotAllowed) {
            return $this->redirect->to('admin.login');
        } catch (InvalidSetupToken) {
            return $this->form($request, $login, ['setup.error.invalid_code', []]);
        } catch (WeakPassword $weakPassword) {
            return $this->form($request, $login, ['error.password_' . $weakPassword->rule, ['limit' => $weakPassword->limit]]);
        } catch (LoginAlreadyTaken|InvalidArgumentException) {
            return $this->form($request, $login, ['setup.error.invalid_login', []]);
        }

        $this->currentUser->login(new UserIdentity($user->id->value));

        return $this->redirect->to('admin.dashboard');
    }

    /**
     * @param array{0: string, 1: array<string, int>}|null $error Translation key and its parameters.
     */
    private function form(ServerRequestInterface $request, string $login, ?array $error): ResponseInterface
    {
        return $this->view->render($request, 'setup', [
            'login' => $login,
            'error' => $error,
            'minPasswordLength' => $this->passwordPolicy->minLength,
        ]);
    }
}
