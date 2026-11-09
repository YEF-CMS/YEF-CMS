<?php

declare(strict_types=1);

namespace Yef\Admin\Logout;

use Psr\Http\Message\ResponseInterface;
use Yef\Admin\Http\Redirect;
use Yiisoft\User\CurrentUser;

/**
 * Signs the user out. Only reachable by POST, so a link on another site cannot do it.
 *
 * @psalm-api Wired through config/common/routes.php.
 */
final readonly class Action
{
    public function __construct(
        private CurrentUser $currentUser,
        private Redirect $redirect,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $this->currentUser->logout();

        return $this->redirect->to('admin.login');
    }
}
