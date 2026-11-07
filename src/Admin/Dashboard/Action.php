<?php

declare(strict_types=1);

namespace Yef\Admin\Dashboard;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Placeholder admin landing page; replaced by the real dashboard on the admin stage.
 *
 * @psalm-api Wired through config/common/routes.php.
 */
final readonly class Action
{
    public function __construct(private ResponseFactoryInterface $responseFactory) {}

    public function __invoke(): ResponseInterface
    {
        $response = $this->responseFactory
            ->createResponse()
            ->withHeader('Content-Type', 'text/html; charset=utf-8');
        $response->getBody()->write('<!doctype html><title>YEF CMS</title><h1>YEF CMS</h1><p>Admin is not implemented yet.</p>');

        return $response;
    }
}
