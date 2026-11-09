<?php

declare(strict_types=1);

namespace Yef\Admin\Http;

use Override;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Answers a form that came without a valid CSRF token.
 *
 * @psalm-api Wired through config/web/di/admin.php.
 */
final readonly class CsrfFailureHandler implements RequestHandlerInterface
{
    public function __construct(private ResponseFactoryInterface $responseFactory) {}

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $response = $this->responseFactory->createResponse(403)->withHeader('Content-Type', 'text/plain; charset=utf-8');
        $response->getBody()->write('Forbidden: the form is out of date or was not sent from this site. Go back, reload the page and try again.');

        return $response;
    }
}
