<?php

declare(strict_types=1);

namespace Yef\Admin\Http;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Headers every admin page gets: nothing is cached, framed or guessed at.
 *
 * @psalm-api Wired through config/web/di/application.php.
 */
final readonly class SecurityHeaders implements MiddlewareInterface
{
    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request)
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Referrer-Policy', 'same-origin');
    }
}
