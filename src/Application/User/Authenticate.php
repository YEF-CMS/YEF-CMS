<?php

declare(strict_types=1);

namespace Yef\Application\User;

use InvalidArgumentException;
use Yef\Domain\User\Exception\LoginAlreadyTaken;
use Yef\Domain\User\Exception\UserStorageFailed;
use Yef\Domain\User\PasswordHasher;
use Yef\Domain\User\User;
use Yef\Domain\User\UserLogin;
use Yef\Domain\User\UserRepository;

/**
 * Checks a login and a password.
 *
 * Every failure looks the same and costs the same time, so the answer does not tell whether a login exists.
 *
 * @psalm-api Used by the admin adapter.
 */
final readonly class Authenticate
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $hasher,
    ) {}

    /**
     * @return User|null Null when the login or the password is wrong.
     *
     * @throws UserStorageFailed
     */
    public function handle(string $login, string $password): ?User
    {
        $user = $this->find($login);
        if (!$user instanceof User) {
            // Spend the time a real check would take.
            $this->hasher->hash($password);

            return null;
        }

        if (!$this->hasher->verify($password, $user->passwordHash)) {
            return null;
        }

        return $this->upgraded($user, $password);
    }

    private function find(string $login): ?User
    {
        try {
            return $this->users->findByLogin(new UserLogin($login));
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Makes the hash again when the hashing settings have become stronger since it was made.
     *
     * @throws UserStorageFailed
     */
    private function upgraded(User $user, string $password): User
    {
        if (!$this->hasher->needsRehash($user->passwordHash)) {
            return $user;
        }

        $upgraded = $user->withPasswordHash($this->hasher->hash($password));
        try {
            $this->users->save($upgraded);
        } catch (LoginAlreadyTaken) {
            // The login is the user's own, so it cannot be taken by someone else.
            return $user;
        }

        return $upgraded;
    }
}
