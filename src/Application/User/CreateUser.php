<?php

declare(strict_types=1);

namespace Yef\Application\User;

use InvalidArgumentException;
use Yef\Domain\Clock;
use Yef\Domain\User\Exception\LoginAlreadyTaken;
use Yef\Domain\User\Exception\UserStorageFailed;
use Yef\Domain\User\PasswordHasher;
use Yef\Domain\User\User;
use Yef\Domain\User\UserIdGenerator;
use Yef\Domain\User\UserLogin;
use Yef\Domain\User\UserRepository;

/**
 * Adds a user who can sign in to the admin.
 *
 * @psalm-api Used by the admin and console adapters.
 */
final readonly class CreateUser
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $hasher,
        private UserIdGenerator $ids,
        private Clock $clock,
        private PasswordPolicy $policy,
    ) {}

    /**
     * @throws InvalidArgumentException When the login is not valid.
     * @throws WeakPassword
     * @throws LoginAlreadyTaken
     * @throws UserStorageFailed
     */
    public function handle(string $login, string $password): User
    {
        $userLogin = new UserLogin($login);
        $this->policy->assertAcceptable($password);

        $user = new User($this->ids->next(), $userLogin, $this->hasher->hash($password), $this->clock->now());
        $this->users->save($user);

        return $user;
    }
}
