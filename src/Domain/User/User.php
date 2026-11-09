<?php

declare(strict_types=1);

namespace Yef\Domain\User;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Someone who signs in to the admin. Roles will be added here when editors appear.
 */
final readonly class User
{
    /**
     * @param string $passwordHash Hash made by a `PasswordHasher`; the password itself is never kept.
     *
     * @throws InvalidArgumentException When the hash is empty.
     */
    public function __construct(
        public UserId $id,
        public UserLogin $login,
        public string $passwordHash,
        public DateTimeImmutable $createdAt,
    ) {
        if ($passwordHash === '') {
            throw new InvalidArgumentException('A user needs a password hash.');
        }
    }

    public function withPasswordHash(string $passwordHash): self
    {
        return new self($this->id, $this->login, $passwordHash, $this->createdAt);
    }
}
