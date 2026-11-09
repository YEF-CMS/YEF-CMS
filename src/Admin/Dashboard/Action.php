<?php

declare(strict_types=1);

namespace Yef\Admin\Dashboard;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yef\Admin\View\AdminView;
use Yef\Application\User\FindUser;
use Yef\Domain\User\User;
use Yiisoft\User\CurrentUser;

/**
 * The first screen after signing in. It only has the frame of the admin for now.
 *
 * @psalm-api Wired through config/common/routes.php.
 */
final readonly class Action
{
    public function __construct(
        private CurrentUser $currentUser,
        private FindUser $findUser,
        private AdminView $view,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->findUser->handle($this->currentUser->getId() ?? '');

        return $this->view->render($request, 'dashboard', [
            'login' => $user instanceof User ? $user->login->value : '',
        ]);
    }
}
