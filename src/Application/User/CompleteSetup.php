<?php

declare(strict_types=1);

namespace Yef\Application\User;

use InvalidArgumentException;
use Yef\Domain\Module\DefaultModules;
use Yef\Domain\Module\Exception\ModuleSettingsFailed;
use Yef\Domain\User\Exception\LoginAlreadyTaken;
use Yef\Domain\User\Exception\UserStorageFailed;
use Yef\Domain\User\SetupToken;
use Yef\Domain\User\User;
use Yef\Domain\User\UserRepository;

use function hash_equals;
use function trim;

/**
 * Installs the CMS on a new site: starts its list of enabled modules and creates the first user.
 * Only someone who can read the setup code from the server's files may do it.
 *
 * @psalm-api Used by the admin adapter.
 */
final readonly class CompleteSetup
{
    public function __construct(
        private UserRepository $users,
        private SetupToken $token,
        private CreateUser $createUser,
        private DefaultModules $defaultModules,
    ) {}

    /**
     * @throws SetupNotAllowed When the site already has a user.
     * @throws InvalidSetupToken
     * @throws InvalidArgumentException When the login is not valid.
     * @throws WeakPassword
     * @throws LoginAlreadyTaken
     * @throws UserStorageFailed
     * @throws ModuleSettingsFailed
     */
    public function handle(string $login, string $password, string $setupCode): User
    {
        if ($this->users->hasAny()) {
            throw SetupNotAllowed::becauseUsersExist();
        }

        if (!hash_equals($this->token->current(), trim($setupCode))) {
            throw InvalidSetupToken::create();
        }

        // Before the user: when the modules cannot be set up, the site is still not installed and setup stays open.
        $this->defaultModules->provide();
        $user = $this->createUser->handle($login, $password);
        $this->token->consume();

        return $user;
    }
}
