<?php

declare(strict_types=1);

namespace Yef\Application\User;

use InvalidArgumentException;
use Yef\Domain\User\Exception\LoginAlreadyTaken;
use Yef\Domain\User\Exception\UserNotFound;
use Yef\Domain\User\Exception\UserStorageFailed;
use Yef\Domain\User\PasswordHasher;
use Yef\Domain\User\UserLogin;
use Yef\Domain\User\UserRepository;

/**
 * Sets a new password for a user, for example one who forgot theirs.
 *
 * @psalm-api Used by the console adapter.
 */
final readonly class ChangePassword
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $hasher,
        private PasswordPolicy $policy,
    ) {}

    /**
     * @throws InvalidArgumentException When the login is not valid.
     * @throws UserNotFound
     * @throws WeakPassword
     * @throws UserStorageFailed
     * @throws LoginAlreadyTaken Never in practice: the login does not change.
     */
    public function handle(string $login, string $password): void
    {
        $userLogin = new UserLogin($login);
        $user = $this->users->findByLogin($userLogin) ?? throw UserNotFound::withLogin($userLogin);
        $this->policy->assertAcceptable($password);

        $this->users->save($user->withPasswordHash($this->hasher->hash($password)));
    }
}
