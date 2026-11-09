<?php

declare(strict_types=1);

namespace Yef\Admin\Authentication;

use Override;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Auth\AuthenticatorWithChallengeInterface;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\User\CurrentUser;

/**
 * Tells the authentication middleware who is signed in (the user kept in the session) and where to send a guest.
 *
 * @psalm-api Wired through config/web/di/admin.php.
 */
final readonly class SessionAuthentication implements AuthenticatorWithChallengeInterface
{
    public function __construct(
        private CurrentUser $currentUser,
        private ResponseFactoryInterface $responseFactory,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    #[Override]
    public function authenticate(ServerRequestInterface $request): ?IdentityInterface
    {
        return $this->currentUser->isGuest() ? null : $this->currentUser->getIdentity();
    }

    #[Override]
    public function challenge(ResponseInterface $response): ResponseInterface
    {
        return $this->responseFactory
            ->createResponse(302)
            ->withHeader('Location', $this->urlGenerator->generate('admin.login'));
    }
}
