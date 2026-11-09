<?php

declare(strict_types=1);

namespace Yef\Application\User;

use function mb_strlen;
use function strlen;

/**
 * The rules a password has to meet. The shortest allowed length is a setting of the site; the longest is
 * 72 bytes, because the hashing algorithm ignores everything after that and would let the rest go unchecked.
 */
final readonly class PasswordPolicy
{
    private const int MAX_BYTES = 72;

    public function __construct(public int $minLength = 6) {}

    /**
     * @throws WeakPassword
     */
    public function assertAcceptable(string $password): void
    {
        if (mb_strlen($password) < $this->minLength) {
            throw WeakPassword::tooShort($this->minLength);
        }

        if (strlen($password) > self::MAX_BYTES) {
            throw WeakPassword::tooLong(self::MAX_BYTES);
        }
    }
}
