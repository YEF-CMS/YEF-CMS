<?php

declare(strict_types=1);

namespace Yef\Domain\User;

use Yef\Domain\User\Exception\LoginAlreadyTaken;
use Yef\Domain\User\Exception\UserStorageFailed;

interface UserRepository
{
    /**
     * Creates the user or replaces the stored one with the same id.
     *
     * @throws LoginAlreadyTaken When another user already has the login.
     * @throws UserStorageFailed
     */
    public function save(User $user): void;

    /**
     * @throws UserStorageFailed
     */
    public function findById(UserId $id): ?User;

    /**
     * @throws UserStorageFailed
     */
    public function findByLogin(UserLogin $login): ?User;

    /**
     * @throws UserStorageFailed
     */
    public function hasAny(): bool;
}
