<?php

declare(strict_types=1);

namespace Yef\Admin\Authentication;

use Override;
use Yef\Application\User\FindUser;
use Yef\Domain\User\User;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Auth\IdentityRepositoryInterface;

/**
 * Lets the Yii user package restore the signed-in user from the id kept in the session.
 * A user who has been deleted since is simply no longer signed in.
 *
 * @psalm-api Wired through config/web/di/admin.php.
 */
final readonly class UserIdentityRepository implements IdentityRepositoryInterface
{
    public function __construct(private FindUser $findUser) {}

    #[Override]
    public function findIdentity(string $id): ?IdentityInterface
    {
        $user = $this->findUser->handle($id);

        return $user instanceof User ? new UserIdentity($user->id->value) : null;
    }
}
