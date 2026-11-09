<?php

declare(strict_types=1);

namespace Yef\Application\User;

use InvalidArgumentException;
use Yef\Domain\User\Exception\UserStorageFailed;
use Yef\Domain\User\User;
use Yef\Domain\User\UserId;
use Yef\Domain\User\UserRepository;

/**
 * Finds a user by the identifier kept in their session.
 *
 * @psalm-api Used by the admin adapter.
 */
final readonly class FindUser
{
    public function __construct(private UserRepository $users) {}

    /**
     * @return User|null Null when the id is unknown or not an id at all.
     *
     * @throws UserStorageFailed
     */
    public function handle(string $id): ?User
    {
        try {
            return $this->users->findById(new UserId($id));
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
