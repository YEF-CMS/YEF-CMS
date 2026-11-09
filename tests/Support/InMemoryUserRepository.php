<?php

declare(strict_types=1);

namespace Yef\Tests\Support;

use Override;
use Yef\Domain\User\Exception\LoginAlreadyTaken;
use Yef\Domain\User\User;
use Yef\Domain\User\UserId;
use Yef\Domain\User\UserLogin;
use Yef\Domain\User\UserRepository;

final class InMemoryUserRepository implements UserRepository
{
    /** @var array<string, User> */
    private array $users = [];

    #[Override]
    public function save(User $user): void
    {
        $owner = $this->findByLogin($user->login);
        if ($owner instanceof User && !$owner->id->equals($user->id)) {
            throw LoginAlreadyTaken::byLogin($user->login);
        }

        $this->users[$user->id->value] = $user;
    }

    #[Override]
    public function findById(UserId $id): ?User
    {
        return $this->users[$id->value] ?? null;
    }

    #[Override]
    public function findByLogin(UserLogin $login): ?User
    {
        foreach ($this->users as $user) {
            if ($user->login->equals($login)) {
                return $user;
            }
        }

        return null;
    }

    #[Override]
    public function hasAny(): bool
    {
        return $this->users !== [];
    }
}
