<?php

declare(strict_types=1);

namespace Yef\Platform;

use Override;
use Yef\Domain\User\PasswordHasher;
use Yiisoft\Security\PasswordHasher as SecurityPasswordHasher;

/**
 * Hashes passwords with PHP's default algorithm through the Yii security package.
 *
 * @psalm-api Wired in through the DI configuration.
 */
final readonly class YiiPasswordHasher implements PasswordHasher
{
    private SecurityPasswordHasher $hasher;

    public function __construct()
    {
        $this->hasher = new SecurityPasswordHasher();
    }

    #[Override]
    public function hash(string $password): string
    {
        return $this->hasher->hash($password);
    }

    #[Override]
    public function verify(string $password, string $hash): bool
    {
        return $this->hasher->validate($password, $hash);
    }

    #[Override]
    public function needsRehash(string $hash): bool
    {
        return $this->hasher->needsRehash($hash);
    }
}
