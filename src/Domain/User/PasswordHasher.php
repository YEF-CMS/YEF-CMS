<?php

declare(strict_types=1);

namespace Yef\Domain\User;

/**
 * One-way hashing of passwords. The algorithm is the implementation's business.
 */
interface PasswordHasher
{
    public function hash(string $password): string;

    public function verify(string $password, string $hash): bool;

    /**
     * Whether the hash was made with weaker settings than the current ones and should be made again.
     */
    public function needsRehash(string $hash): bool;
}
