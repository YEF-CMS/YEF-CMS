<?php

declare(strict_types=1);

namespace Yef\Application\User;

use Yef\Domain\User\Exception\UserStorageFailed;
use Yef\Domain\User\SetupToken;
use Yef\Domain\User\UserRepository;

/**
 * Tells whether the site still has to be set up (it has no user yet) and makes sure the setup code exists.
 *
 * @psalm-api Used by the admin adapter.
 */
final readonly class PrepareSetup
{
    public function __construct(
        private UserRepository $users,
        private SetupToken $token,
    ) {}

    /**
     * @return bool True when the first user has to be created.
     *
     * @throws UserStorageFailed
     */
    public function handle(): bool
    {
        if ($this->users->hasAny()) {
            return false;
        }

        $this->token->current();

        return true;
    }
}
