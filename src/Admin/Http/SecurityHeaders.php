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
    /**
     * Scripts only from the admin itself. Styles may be inline: the editor and the progress bar need it.
     * Images and frames may come from anywhere over https, since pages link to pictures and embeds on other sites
     * and the editor shows them as they are.
     */
    private const string POLICY = "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; "
        . "img-src 'self' data: blob: https:; frame-src https:; font-src 'self' data:; object-src 'none'; "
        . "base-uri 'self'; form-action 'self'; frame-ancestors 'none'";

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request)
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Referrer-Policy', 'same-origin');

        // A response that sets its own policy, like the sandboxed preview, keeps it.
        return $response->hasHeader('Content-Security-Policy')
            ? $response
            : $response->withHeader('Content-Security-Policy', self::POLICY);
    }
}
