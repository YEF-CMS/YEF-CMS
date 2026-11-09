<?php

declare(strict_types=1);

namespace Yef\Admin\Http;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Router\UrlGeneratorInterface;

/**
 * A redirect to a named admin route, in the URL mode the visitor is in.
 *
 * @psalm-api Used by the admin actions.
 */
final readonly class Redirect
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function to(string $routeName): ResponseInterface
    {
        return $this->responseFactory
            ->createResponse(303)
            ->withHeader('Location', $this->urlGenerator->generate($routeName));
    }
}
