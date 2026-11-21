<?php

declare(strict_types=1);

namespace Yef\Admin\Http;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Router\UrlGeneratorInterface;

use function is_array;
use function parse_url;
use function str_starts_with;

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
        return $this->redirectTo($this->urlGenerator->generate($routeName));
    }

    /**
     * @param array<string, string> $arguments The values of the route's placeholders.
     */
    public function toWith(string $routeName, array $arguments): ResponseInterface
    {
        return $this->redirectTo($this->urlGenerator->generate($routeName, $arguments));
    }

    /**
     * Back to the page the request came from, if that page belongs to this site; otherwise to the named route.
     * Only the path and query of the referring page are used, so a page on another site cannot send the visitor away.
     */
    public function backOr(ServerRequestInterface $request, string $routeName): ResponseInterface
    {
        $referrer = parse_url($request->getHeaderLine('Referer'));
        $uri = $request->getUri();

        if (is_array($referrer)
            && ($referrer['host'] ?? null) === $uri->getHost()
            && ($referrer['port'] ?? null) === $uri->getPort()
        ) {
            $location = ($referrer['path'] ?? '/') . (isset($referrer['query']) ? '?' . $referrer['query'] : '');
            if (str_starts_with($location, '/') && !str_starts_with($location, '//')) {
                return $this->redirectTo($location);
            }
        }

        return $this->to($routeName);
    }

    private function redirectTo(string $location): ResponseInterface
    {
        return $this->responseFactory->createResponse(303)->withHeader('Location', $location);
    }
}
