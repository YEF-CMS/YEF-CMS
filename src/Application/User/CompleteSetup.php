<?php

declare(strict_types=1);

namespace Yef\Application\User;

use InvalidArgumentException;
use Yef\Domain\User\Exception\LoginAlreadyTaken;
use Yef\Domain\User\Exception\UserStorageFailed;
use Yef\Domain\User\SetupToken;
use Yef\Domain\User\User;
use Yef\Domain\User\UserRepository;

use function hash_equals;
use function trim;

/**
 * Creates the first user of a new site. Only someone who can read the setup code from the server's files may do it.
 *
 * @psalm-api Used by the admin adapter.
 */
final readonly class CompleteSetup
{
    public function __construct(
        private UserRepository $users,
        private SetupToken $token,
        private CreateUser $createUser,
    ) {}

    /**
     * @throws SetupNotAllowed When the site already has a user.
     * @throws InvalidSetupToken
     * @throws InvalidArgumentException When the login is not valid.
     * @throws WeakPassword
     * @throws LoginAlreadyTaken
     * @throws UserStorageFailed
     */
    public function handle(string $login, string $password, string $setupCode): User
    {
        if ($this->users->hasAny()) {
            throw SetupNotAllowed::becauseUsersExist();
        }

        if (!hash_equals($this->token->current(), trim($setupCode))) {
            throw InvalidSetupToken::create();
        }

        $user = $this->createUser->handle($login, $password);
        $this->token->consume();

        return $user;
    }
}
