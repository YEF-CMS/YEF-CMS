<?php

declare(strict_types=1);

namespace Yef\Tests\Support;

use Override;
use Yef\Domain\User\PasswordHasher;

use function str_starts_with;
use function substr;

/**
 * A readable stand-in for a real hasher: "hash:<password>", or "old:<password>" for hashes that need to be made again.
 */
final class FakePasswordHasher implements PasswordHasher
{
    /** @var list<string> Passwords that were hashed, in order. */
    public array $hashed = [];

    #[Override]
    public function hash(string $password): string
    {
        $this->hashed[] = $password;

        return 'hash:' . $password;
    }

    #[Override]
    public function verify(string $password, string $hash): bool
    {
        return str_starts_with($hash, 'old:')
            ? substr($hash, 4) === $password
            : $hash === 'hash:' . $password;
    }

    #[Override]
    public function needsRehash(string $hash): bool
    {
        return str_starts_with($hash, 'old:');
    }
}
