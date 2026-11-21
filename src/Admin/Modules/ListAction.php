<?php

declare(strict_types=1);

namespace Yef\Admin\Modules;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The installed modules with their states and the buttons that switch them.
 *
 * @psalm-api Wired through config/common/routes.php.
 */
final readonly class ListAction
{
    public function __construct(private ModuleListRenderer $renderer) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        return $this->renderer->render($request);
    }
}
