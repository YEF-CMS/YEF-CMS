<?php

declare(strict_types=1);

namespace Yef\Admin\NotFound;

use Override;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * @psalm-api Wired through config/web/di/application.php.
 */
final readonly class NotFoundHandler implements RequestHandlerInterface
{
    public function __construct(private ResponseFactoryInterface $responseFactory) {}

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $response = $this->responseFactory
            ->createResponse(404)
            ->withHeader('Content-Type', 'text/html; charset=utf-8');
        $response->getBody()->write('<!doctype html><title>Not found</title><h1>404 Not found</h1>');

        return $response;
    }
}
